<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use App\Services\Events\StreamAnalyticsWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class ConsumeLinkEvents extends Command
{
    protected $signature='events:consume {group=analytics-writer} {--consumer=} {--once} {--count=500} {--stream=} {--skip-ack} {--fault=} {--claim-idle-ms=60000}';
    protected $description='Consume click facts, commit idempotent analytics, then acknowledge';
    public function handle(EventStream $stream, StreamAnalyticsWriter $writer): int
    {
        $group=$this->argument('group');
        if (!in_array($group,['analytics-writer','milestones'],true)) { $this->error('Unknown implemented handler'); return 1; }
        $key=$this->option('stream') ?: config('events.stream');
        if ($key !== config('events.stream') && !str_starts_with($key,'lab5:test:')) throw new RuntimeException('Unsupported stream key');
        if ($this->option('skip-ack') && (!app()->environment('local','testing') || !str_starts_with($key,'lab5:test:'))) throw new RuntimeException('Skip ack is test-only');
        $fault = (string)($this->option('fault') ?? '');
        $idle = (int)$this->option('claim-idle-ms');
        if (!in_array($fault, ['', 'after-read', 'after-commit'], true)) throw new RuntimeException('Unsupported fault point');
        if ($idle < 100 || $idle > 60000) throw new RuntimeException('Invalid claim idle timeout');
        if ($fault !== '' || $idle !== 60000) {
            if (!app()->environment('local','testing') || getenv('LAB7_FAILURE_DRILLS') !== '1'
                || !str_starts_with($key, 'lab5:test:lab7:') || $group !== 'analytics-writer') {
                throw new RuntimeException('Fault injection and shortened idle timeout require an explicitly enabled isolated Lab 7 stream');
            }
        }
        $name=$this->option('consumer') ?: gethostname().'-'.getmypid();
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$name)) throw new RuntimeException('Invalid consumer name');
        $count=max(1,min(500,(int)$this->option('count')));
        $redis=$stream->client(); $redis->setOption(\Redis::OPT_READ_TIMEOUT,2.0);
        $lastMaintenanceWarningAt = 0.0;
        try {
            do {
                try {
                    // Own pending first, then abandoned work, then new work.
                    $reply=$redis->rawCommand('XREADGROUP','GROUP',$group,$name,'COUNT',(string)$count,'STREAMS',$key,'0');
                    $entries=$reply[0][1]??[];
                    if (!$entries) {
                        $claimed=$redis->rawCommand('XAUTOCLAIM',$key,$group,$name,(string)$idle,'0-0','COUNT',(string)$count);
                        $entries=$claimed[1]??[];
                    }
                    if (!$entries) {
                        $reply=$redis->rawCommand('XREADGROUP','GROUP',$group,$name,'COUNT',(string)$count,'BLOCK','1000','STREAMS',$key,'>');
                        $entries=$reply[0][1]??[];
                    }
                    if ($entries) $this->lab7FaultPoint('after-read', $key);
                    $events=[]; $acks=[]; $deferredMalformed=false;
                    foreach ($entries as [$id,$fields]) {
                        try {
                            if (!is_array($fields)) throw new RuntimeException('Trimmed pending entry');
                            $fields=\App\Services\Events\StreamFailures::fields($fields);
                            $event=app(\App\Services\Events\EventEnvelope::class)->decode($fields['event']??'');
                            if ($event['event_type']==='LinkClicked') {
                                $events[]=$event;
                            }
                            $acks[]=$id;
                        } catch (\Throwable $e) {
                            $failure=app(\App\Services\Events\StreamFailures::class)->malformed($redis,$group,$key,(string)$id,$fields,$e);
                            $deferredMalformed=$failure['status']!==2;
                            if($failure['status']!==0)$this->warn(json_encode(['malformed_source_id'=>$id,'attempt'=>$failure['attempts'],'dead_id'=>$failure['dead_id']]));
                        }
                    }
                    config(['events.stream'=>$key]);
                    $handler=$group==='analytics-writer'?$writer:app(\App\Services\Events\StreamMilestoneProjector::class);
                    $inserted=$handler->persist($events,$name);
                    if ($acks) $this->lab7FaultPoint('after-commit', $key);
                    if ($acks && !$this->option('skip-ack')) {
                        $redis->rawCommand('XACK',$key,$group,...$acks);
                        $redis->rawCommand('HDEL',\App\Services\Events\StreamFailures::retryKey($group,$key),...$acks);
                    }
                    if($deferredMalformed && !$acks)usleep(100000);
                    if ($acks) $this->line(json_encode(['consumer'=>$name,'delivered'=>count($acks),'inserted'=>$inserted,'acked'=>!$this->option('skip-ack')]));
                } catch (\App\Exceptions\AnalyticsMaintenanceBusy $e) {
                    if ($this->option('once')) { $this->warn($e->getMessage()); return 1; }
                    if (microtime(true) - $lastMaintenanceWarningAt >= 5) {
                        $this->warn($e->getMessage());
                        $lastMaintenanceWarningAt = microtime(true);
                    }
                    // The database already waited up to one second; retry briefly if still busy.
                    usleep(100000);
                } catch (\RedisException $e) {
                    $this->error('Stream connection lost; exiting for supervised reconnect without acknowledging pending entries');
                    return self::FAILURE;
                } catch (\Throwable $e) {
                    $this->error(get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
                    if ($this->option('once')) return 1;
                    sleep(1);
                }
            } while (!$this->option('once'));
        } finally { $redis->close(); }
        return 0;
    }
    private function lab7FaultPoint(string $phase, string $key): void
    {
        if ($this->option('fault') !== $phase) return;
        // handle() checks the opt-in flag, local environment, and isolated stream prefix.
        $this->line(json_encode(['lab7_fault_reached'=>$phase,'stream'=>$key]));
        if (defined('STDOUT')) fflush(STDOUT);
        while (true) usleep(100000);
    }

}
