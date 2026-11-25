<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use App\Services\Events\StreamFailures;
use Illuminate\Console\Command;

class ListDeadEvents extends Command
{
    protected $signature='events:dead:list {--group=analytics-writer} {--limit=20} {--source=}';
    protected $description='List dead-letter metadata without dumping event payloads';
    public function handle(EventStream $stream): int
    {
        $group=(string)$this->option('group');
        if(!in_array($group,['analytics-writer','dashboard','milestones','webhooks'],true))throw new \InvalidArgumentException('Unsupported consumer group');
        $redis=$stream->client();
        try{
            $reply=$redis->rawCommand('XREVRANGE','events:dead-letter:'.$group,'+','-','COUNT',(string)max(1,min(1000,(int)$this->option('limit'))));
            $rows=[];
            foreach(($reply?:[])as[$id,$fields]){
                $row=StreamFailures::fields($fields);
                if($this->option('source') && ($row['source_stream']??'')!==$this->option('source'))continue;
                $rows[]=['id'=>$id,'source_stream'=>$row['source_stream']??null,'source_id'=>$row['source_id']??null,'attempt_count'=>(int)($row['attempt_count']??0),'reason'=>$row['reason']??null];
            }
            $this->line(json_encode($rows,JSON_THROW_ON_ERROR));
        }finally{$redis->close();}
        return 0;
    }
}
