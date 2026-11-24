<?php
namespace App\Console\Commands;
use App\Events\LinkCountUpdated;
use App\Services\Events\EventStream;
use App\Services\Dashboard\DashboardProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ConsumeDashboardEvents extends Command{
    protected $signature='events:dashboard {--once} {--consumer=dashboard-1}';
    protected $description='Consume the dashboard group, persist catch-up history and broadcast committed counts';
    public function handle(EventStream $stream,DashboardProjection $projection):int{
        $redis=$stream->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2.0);$key=config('events.stream');$name=$this->option('consumer');
        try{$redis->rawCommand('XGROUP','CREATE',$key,'dashboard','0','MKSTREAM');}catch(\RedisException $e){if(!str_contains($e->getMessage(),'BUSYGROUP'))throw $e;}
        try{do{try{
            $r=$redis->rawCommand('XREADGROUP','GROUP','dashboard',$name,'COUNT','100','STREAMS',$key,'0');$entries=$r[0][1]??[];
            if(!$entries){$r=$redis->rawCommand('XAUTOCLAIM',$key,'dashboard',$name,'60000','0-0','COUNT','100');$entries=$r[1]??[];}
            if(!$entries){$r=$redis->rawCommand('XREADGROUP','GROUP','dashboard',$name,'COUNT','100','BLOCK','1000','STREAMS',$key,'>');$entries=$r[0][1]??[];}
            foreach($entries as[$entryId,$fields]){
                $map=[];if(is_array($fields))for($i=0;$i<count($fields);$i+=2)$map[$fields[$i]]=$fields[$i+1];
                try{$event=json_decode($map['event']??'',true,512,JSON_THROW_ON_ERROR);
                    if(!Str::isUuid($event['event_id']??'')||($event['schema_version']??null)!==1)throw new \RuntimeException('Invalid envelope');
                    if(($event['event_type']??'')==='LinkClicked'&&(!is_int($event['payload']['link_id']??null)||!isset($event['occurred_at'])))throw new \RuntimeException('Invalid click');
                }catch(\Throwable $e){
                    $dlq=$redis->rawCommand('XADD','events:dead-letter:dashboard','MAXLEN','~','10000','*','source_id',$entryId,'reason','invalid_envelope','entry',json_encode($fields));
                    if(!$dlq)throw new \RuntimeException('Dashboard DLQ unavailable');
                    $redis->rawCommand('XACK',$key,'dashboard',$entryId);continue;
                }
                if($event['event_type']==='LinkClicked'){
                    $row=$projection->store($event);
                    if($row->broadcasted_at===null){
                        $broadcastStarted = microtime(true);
                        event(new LinkCountUpdated($projection->payload($row)));
                        $broadcastMs = (microtime(true) - $broadcastStarted) * 1000;
                        if ($broadcastMs >= 100) $this->line(json_encode(['lab6_trace'=>'broadcast_slow','at'=>gmdate('c'),'elapsed_ms'=>round($broadcastMs,3)]));
                        DB::table('dashboard_events')->where('id',$row->id)->update(['broadcasted_at'=>now('UTC')]);
                    }
                }
                $redis->rawCommand('XACK',$key,'dashboard',$entryId);
            }
            if($entries)$this->line(json_encode(['dashboard_entries'=>count($entries)]));
        }catch(\LogicException $e){if($this->option('once'))return 1;usleep(50000);}
        catch(\Throwable $e){$this->error(get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());if($this->option('once'))return 1;sleep(1);}
        }while(!$this->option('once'));}finally{$redis->close();}return 0;
    }
}
