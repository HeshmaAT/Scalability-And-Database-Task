<?php
namespace App\Console\Commands;
use App\Services\Events\EventEnvelope;
use App\Services\Events\EventStream;
use App\Services\Events\StreamFailures;
use Illuminate\Console\Command;

class ReplayDeadEvent extends Command
{
    protected $signature='events:dead:replay {id} {--group=analytics-writer} {--payload-file=}';
    protected $description='Validate and replay a dead-letter envelope with its stable event UUID';
    public function handle(EventStream $stream, EventEnvelope $validator): int
    {
        $id=(string)$this->argument('id');$group=(string)$this->option('group');
        if(!preg_match('/^\d+-\d+$/',$id)||!in_array($group,['analytics-writer','dashboard','milestones','webhooks'],true))throw new \InvalidArgumentException('Invalid dead-letter reference');
        $redis=$stream->client();
        try{
            $rows=$redis->rawCommand('XRANGE','events:dead-letter:'.$group,$id,$id,'COUNT','1');
            if(!$rows)throw new \RuntimeException('Dead-letter entry not found');
            $dead=StreamFailures::fields($rows[0][1]);$source=$dead['source_stream']??'';
            if($source!==config('events.stream')&&!str_starts_with($source,'lab5:test:'))throw new \RuntimeException('Unsupported source stream');
            $fields=json_decode($dead['entry']??'',true,512,JSON_THROW_ON_ERROR);
            if(!is_array($fields))throw new \RuntimeException('Missing original fields');
            if(array_is_list($fields))$fields=StreamFailures::fields($fields);
            $original=$fields['event']??'';
            $json=$this->option('payload-file')?file_get_contents($this->option('payload-file')):$original;
            if(!is_string($json))throw new \RuntimeException('Could not read corrected envelope');
            $event=$validator->decode($json);$old=json_decode($original,true);
            if(is_array($old)&&is_string($old['event_id']??null)&&$old['event_id']!==$event['event_id'])throw new \RuntimeException('Replay must preserve the original event_id');
            $lua=<<<'LUA'
local appended=redis.call('XADD',KEYS[1],'MAXLEN','~',ARGV[1],'*','event',ARGV[2])
redis.call('XADD',KEYS[2],'MAXLEN','~','10000','*','dead_id',ARGV[3],'source_id',ARGV[4],'new_source_id',appended,'event_id',ARGV[5])
return appended
LUA;
            $new=$redis->rawCommand('EVAL',$lua,'2',$source,'events:dead-replays:'.$group,(string)config('events.maxlen'),json_encode($event,JSON_THROW_ON_ERROR),$id,$dead['source_id']??'',$event['event_id']);
            if(!is_string($new))throw new \RuntimeException('Replay append failed');
            $this->line(json_encode(['dead_id'=>$id,'source_stream'=>$source,'new_source_id'=>$new,'event_id'=>$event['event_id']],JSON_THROW_ON_ERROR));
        }finally{$redis->close();}
        return 0;
    }
}
