<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use App\Services\Events\StreamAnalyticsWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class ConsumeLinkEvents extends Command
{
    protected $signature='events:consume {group=analytics-writer} {--consumer=} {--once} {--count=500} {--stream=} {--skip-ack}';
    protected $description='Consume click facts, commit idempotent analytics, then acknowledge';
    public function handle(EventStream $stream, StreamAnalyticsWriter $writer): int
    {
        $group=$this->argument('group');
        if (!in_array($group,['analytics-writer','milestones'],true)) { $this->error('Unknown implemented handler'); return 1; }
        $key=$this->option('stream') ?: config('events.stream');
        if ($key !== config('events.stream') && !str_starts_with($key,'lab5:test:')) throw new RuntimeException('Unsupported stream key');
        if ($this->option('skip-ack') && (!app()->environment('local','testing') || !str_starts_with($key,'lab5:test:'))) throw new RuntimeException('Skip ack is test-only');
        $name=$this->option('consumer') ?: gethostname().'-'.getmypid();
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$name)) throw new RuntimeException('Invalid consumer name');
        $count=max(1,min(500,(int)$this->option('count')));
        $redis=$stream->client(); $redis->setOption(\Redis::OPT_READ_TIMEOUT,2.0);
        try {
            do {
                try {
                    // Own pending first, then abandoned work, then new work.
                    $reply=$redis->rawCommand('XREADGROUP','GROUP',$group,$name,'COUNT',(string)$count,'STREAMS',$key,'0');
                    $entries=$reply[0][1]??[];
                    if (!$entries) {
                        $claimed=$redis->rawCommand('XAUTOCLAIM',$key,$group,$name,'60000','0-0','COUNT',(string)$count);
                        $entries=$claimed[1]??[];
                    }
                    if (!$entries) {
                        $reply=$redis->rawCommand('XREADGROUP','GROUP',$group,$name,'COUNT',(string)$count,'BLOCK','1000','STREAMS',$key,'>');
                        $entries=$reply[0][1]??[];
                    }
                    $events=[]; $acks=[];
                    foreach ($entries as [$id,$fields]) {
                        try {
                            if (!is_array($fields)) throw new RuntimeException('Trimmed pending entry');
                            $fields=array_combine(array_filter($fields,fn($k)=>$k%2===0,ARRAY_FILTER_USE_KEY),array_filter($fields,fn($k)=>$k%2===1,ARRAY_FILTER_USE_KEY));
                            $event=json_decode($fields['event']??'',true,512,JSON_THROW_ON_ERROR);
                            if (($event['schema_version']??null)!==1 || !Str::isUuid($event['event_id']??'')) throw new RuntimeException('Invalid event contract');
                            if (($event['event_type']??'')==='LinkClicked') {
                                $p=$event['payload']??[];
                                if (!is_int($p['link_id']??null) || $p['link_id']<1 || !preg_match('/^[a-f0-9]{64}$/',$p['visitor_hash']??'')) throw new RuntimeException('Invalid click payload');
                                $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$p['clicked_at']??'',new \DateTimeZone('UTC'));
                                if (!$date || $date->format('Y-m-d H:i:s')!==$p['clicked_at']) throw new RuntimeException('Invalid clicked_at');
                                $events[]=$event;
                            }
                            $acks[]=$id;
                        } catch (\Throwable $e) {
                            $dlq=$redis->rawCommand('XADD','events:dead-letter:'.$group,'MAXLEN','~','10000','*','source_stream',$key,'source_id',$id,'reason','invalid_event','entry',json_encode($fields));
                            if (!$dlq) throw new RuntimeException('Dead-letter write failed');
                            $redis->rawCommand('XACK',$key,$group,$id);
                            $this->warn('Dead-lettered malformed entry '.$id);
                        }
                    }
                    config(['events.stream'=>$key]);
                    $handler=$group==='analytics-writer'?$writer:app(\App\Services\Events\StreamMilestoneProjector::class);
                    $inserted=$handler->persist($events,$name);
                    if ($acks && !$this->option('skip-ack')) $redis->rawCommand('XACK',$key,$group,...$acks);
                    if ($acks) $this->line(json_encode(['consumer'=>$name,'delivered'=>count($acks),'inserted'=>$inserted,'acked'=>!$this->option('skip-ack')]));
                } catch (\Throwable $e) {
                    $this->error(get_class($e).': '.$e->getMessage());
                    if ($this->option('once')) return 1;
                    sleep(1);
                }
            } while (!$this->option('once'));
        } finally { $redis->close(); }
        return 0;
    }
}
