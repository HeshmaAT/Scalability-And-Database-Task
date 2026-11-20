<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class RelayEventOutbox extends Command
{
    protected $signature='events:relay {--once} {--stream=}';
    protected $description='Publish committed outbox envelopes with stable event IDs';
    public function handle(EventStream $stream):int{
        $key=$this->option('stream')?:config('events.stream');
        if($key!==config('events.stream')&&!str_starts_with($key,'lab5:test:'))throw new \RuntimeException('Unsupported stream');
        do{
            try{
                $sent=DB::transaction(function()use($stream,$key){
                    $rows=DB::table('event_outbox')->where('stream_name',$key)->whereNull('published_at')->orderBy('created_at')->limit(20)->lock('FOR UPDATE SKIP LOCKED')->get();
                    if($rows->isEmpty())return 0;
                    $redis=$stream->client();
                    try{
                        foreach($rows as $row){
                            $id=$redis->rawCommand('XADD',$key,'MAXLEN','~',(string)config('events.maxlen'),'*','event',$row->envelope);
                            if(!is_string($id))throw new \RuntimeException('Outbox append failed');
                            DB::table('event_outbox')->where('event_id',$row->event_id)->update(['published_at'=>now('UTC')]);
                        }
                    }finally{$redis->close();}
                    return $rows->count();
                });
                if($sent)$this->line(json_encode(['outbox_published'=>$sent]));
                if(!$sent&&!$this->option('once'))usleep(200000);
            }catch(\Throwable $e){$this->error(get_class($e).': '.$e->getMessage());if($this->option('once'))return 1;sleep(1);}
        }while(!$this->option('once'));
        return 0;
    }
}
