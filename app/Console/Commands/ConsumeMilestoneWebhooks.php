<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ConsumeMilestoneWebhooks extends Command
{
    protected $signature='events:webhooks {--once} {--stream=} {--consumer=webhooks-1}';
    protected $description='Turn milestone facts into durable deduplicated HTTP delivery records';
    public function handle(EventStream $stream):int{
        $key=$this->option('stream')?:config('events.stream');
        if($key!==config('events.stream')&&!str_starts_with($key,'lab5:test:'))throw new \RuntimeException('Unsupported stream');
        $redis=$stream->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2.0);$name=$this->option('consumer');
        try{$redis->rawCommand('XGROUP','CREATE',$key,'webhooks','0','MKSTREAM');}catch(\RedisException $e){if(!str_contains($e->getMessage(),'BUSYGROUP'))throw $e;}
        try{do{try{
            $reply=$redis->rawCommand('XREADGROUP','GROUP','webhooks',$name,'COUNT','100','STREAMS',$key,'0');$entries=$reply[0][1]??[];
            if(!$entries){$claim=$redis->rawCommand('XAUTOCLAIM',$key,'webhooks',$name,'60000','0-0','COUNT','100');$entries=$claim[1]??[];}
            if(!$entries){$reply=$redis->rawCommand('XREADGROUP','GROUP','webhooks',$name,'COUNT','100','BLOCK','1000','STREAMS',$key,'>');$entries=$reply[0][1]??[];}
            foreach($entries as [$entryId,$fields]){
                $event=json_decode($fields[1]??'',true,512,JSON_THROW_ON_ERROR);
                if(($event['event_type']??'')==='MilestoneReached'){
                    if(!Str::isUuid($event['event_id']??'')||($event['schema_version']??null)!==1||!is_int($event['payload']['link_id']??null))throw new \RuntimeException('Invalid milestone contract');
                    DB::transaction(function()use($event){
                        $sub=DB::table('link_webhooks')->useWritePdo()->where('link_id',$event['payload']['link_id'])->where('enabled',true)->first();
                        if(!$sub)return;
                        $body=json_encode($event,JSON_THROW_ON_ERROR);$now=now('UTC');
                        DB::table('webhook_deliveries')->insertOrIgnore(['event_id'=>$event['event_id'],'link_id'=>$event['payload']['link_id'],'url'=>$sub->url,'secret'=>$sub->secret,'body'=>$body,'status'=>'pending','attempts'=>0,'available_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
                        $stored=DB::table('webhook_deliveries')->useWritePdo()->where('event_id',$event['event_id'])->value('body');
                        if($stored!==$body)throw new \RuntimeException('Event ID payload conflict');
                    });
                }
                $redis->rawCommand('XACK',$key,'webhooks',$entryId);
            }
            if($entries)$this->line(json_encode(['webhook_events_seen'=>count($entries)]));
        }catch(\Throwable $e){$this->error(get_class($e).': '.$e->getMessage());if($this->option('once'))return 1;sleep(1);}}while(!$this->option('once'));}
        finally{$redis->close();}return 0;
    }
}
