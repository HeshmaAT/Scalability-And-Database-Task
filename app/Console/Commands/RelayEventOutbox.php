<?php
namespace App\Console\Commands;
use App\Services\Events\EventStream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class RelayEventOutbox extends Command
{
    protected $signature='events:relay {--once} {--stream=} {--fault=} {--claim-delay-ms=0} {--barrier=}';
    protected $description='Publish committed outbox envelopes with stable event IDs';
    public function handle(EventStream $stream):int{
        $key=$this->option('stream')?:config('events.stream');
        if($key!==config('events.stream')&&!str_starts_with($key,'lab5:test:'))throw new \RuntimeException('Unsupported stream');
        $fault=(string)($this->option('fault')??'');$delay=(int)$this->option('claim-delay-ms');$barrier=(string)($this->option('barrier')??'');
        if(!in_array($fault,['','after-publish'],true)||$delay<0||$delay>1000)throw new \RuntimeException('Unsupported relay fault');
        if(($fault!==''||$delay!==0||$barrier!=='')&&(!app()->environment('local','testing')||getenv('LAB7_FAILURE_DRILLS')!=='1'||!str_starts_with($key,'lab5:test:lab7:')))throw new \RuntimeException('Relay fault requires explicitly enabled isolated Lab 7 stream');
        if($barrier!==''){
            if(!str_starts_with($barrier,$key.':barrier:'))throw new \RuntimeException('Invalid relay barrier');
            $coordination=$stream->client();
            try{
                $coordination->rawCommand('SADD',$barrier,gethostname().'-'.getmypid());$coordination->rawCommand('EXPIRE',$barrier,'60');
                $deadline=microtime(true)+30;
                while((int)$coordination->rawCommand('SCARD',$barrier)<2){if(microtime(true)>=$deadline)throw new \RuntimeException('Relay barrier needs two ready participants');usleep(100000);}
            }finally{$coordination->close();}
        }
        do{
            try{
                $sent=DB::transaction(function()use($stream,$key,$fault,$delay){
                    $rows=DB::table('event_outbox')->where('stream_name',$key)->whereNull('published_at')->orderBy('created_at')->orderBy('event_id')->limit(20)->lock('FOR UPDATE SKIP LOCKED')->get();
                    if($rows->isEmpty())return 0;
                    if($delay)usleep($delay*1000);
                    $redis=$stream->client();
                    try{
                        foreach($rows as $row){
                            $id=$redis->rawCommand('XADD',$key,'MAXLEN','~',(string)config('events.maxlen'),'*','event',$row->envelope);
                            if(!is_string($id))throw new \RuntimeException('Outbox append failed');
                            if($fault==='after-publish'){
                                $this->line(json_encode(['lab7_fault_reached'=>'after-publish','event_id'=>$row->event_id]));
                                if(defined('STDOUT'))fflush(STDOUT);
                                while(true)usleep(100000);
                            }
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
