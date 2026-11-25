<?php
namespace App\Console\Commands;
use App\Models\Link;
use App\Models\User;
use App\Services\ClickMaintenanceService;
use App\Services\Events\ClickReplayCoverage;
use App\Services\Events\EventStream;
use App\Services\Events\StreamAnalyticsWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RunLab7ClickRebuild extends Command
{
    protected $signature='lab7:click-rebuild';
    protected $description='Local isolated counter corruption, retained replay and trimmed-history fallback drill';
    public function handle(): int
    {
        if (!app()->environment('local','testing') || getenv('LAB7_FAILURE_DRILLS')!=='1') {
            throw new RuntimeException('Explicit local lab opt-in required');
        }
        $link=Link::forceCreate(['user_id'=>User::query()->useWritePdo()->firstOrFail()->id,
            'short_code'=>'lab7rb_'.bin2hex(random_bytes(7)),
            'destination_url'=>'https://example.com/lab7-rebuild','is_active'=>true]);
        $source='lab5:test:lab7:rebuild:'.bin2hex(random_bytes(8));
        $redis=app(EventStream::class)->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,5);
        $events=[];$amount=1000;
        try {
            for($i=0;$i<$amount;$i++) {
                $event=['event_id'=>(string)Str::uuid(),'event_type'=>'LinkClicked',
                    'occurred_at'=>now('UTC')->format('Y-m-d\TH:i:s.u\Z'),'schema_version'=>1,
                    'ordering_key'=>(string)$link->id,'payload'=>['link_id'=>$link->id,
                        'clicked_at'=>now('UTC')->format('Y-m-d H:i:s'),'visitor_hash'=>hash('sha256','lab7-counter')]];
                $events[]=$event;
                $reply=$redis->rawCommand('XADD',$source,'*','event',json_encode($event,JSON_THROW_ON_ERROR));
                if(!is_string($reply))throw new RuntimeException('Append failed');
            }
            // Duplicate delivery, same UUID: replay must count it once.
            $redis->rawCommand('XADD',$source,'*','event',json_encode($events[0],JSON_THROW_ON_ERROR));
            foreach(array_chunk($events,500) as $batch) {
                app(StreamAnalyticsWriter::class)->persist($batch,'lab7-rebuild-fixture');
            }
            $before=$this->state($link->id);
            if($before['counter']!==1000 || $before['raw']!==1000 || $before['ledger']!==1000)throw new RuntimeException('Fixture persistence mismatch');
            DB::table('links')->where('id',$link->id)->update(['click_count'=>7]);
            $started=microtime(true);$rebuilt=$this->replace($redis,$source,$link->id);
            $elapsed=microtime(true)-$started;$after=$this->state($link->id);
            if($rebuilt!==1000 || $after!==$before)throw new RuntimeException('Replay failed');
            // An exact trim of this dedicated fixture stream cannot touch live history.
            $redis->rawCommand('XTRIM',$source,'MAXLEN','=','100');
            DB::table('links')->where('id',$link->id)->update(['click_count'=>7]);
            $rejected=false;$reason=null;
            try{$this->replace($redis,$source,$link->id);}
            catch(RuntimeException $e){
                if(!str_contains($e->getMessage(),'Incomplete retained history'))throw $e;
                $rejected=true;$reason=$e->getMessage();
            }
            if(!$rejected || $this->state($link->id)['counter']!==7)throw new RuntimeException('Partial replay was not safely rejected');
            // Existing production repair path: one explicit fixture link, never all links.
            $fallbackStarted=microtime(true);
            $repaired=false;
            for($attempt=0;$attempt<30;$attempt++) {
                if($this->call('stats:rebuild',['--link'=>(string)$link->id])===0){$repaired=true;break;}
                usleep(100000);
            }
            if(!$repaired)throw new RuntimeException('Fallback unavailable; retry after maintenance');
            $fallbackSeconds=microtime(true)-$fallbackStarted;$final=$this->state($link->id);
            if($final!==$before)throw new RuntimeException('SQL fallback mismatch');
            $this->line(json_encode(['passed'=>true,'link_id'=>$link->id,'source'=>$source,
                'source_entries_before_trim'=>1001,'unique_click_events'=>1000,'corrupted_counter'=>7,
                'rebuild_seconds'=>round($elapsed,6),'after_event_replay'=>$after,
                'retained_after_trim'=>(int)$redis->rawCommand('XLEN',$source),
                'partial_replay_rejected'=>$rejected,'refusal_reason'=>$reason,
                'fallback_seconds'=>round($fallbackSeconds,6),'after_sql_fallback'=>$final,
                'method'=>'Replaced only a dedicated fixture counter under the shared maintenance lock. Existing raw rows and idempotency ledger preserved. Replay verifies every persisted UUID and envelope hash before replacement.',
                'limitations'=>'Replay covers this isolated fresh link with complete known ledger and no concurrent clicks to it. Fallback counts persisted raw clicks plus archived aggregate; it cannot recover unpublished or trimmed-before-persistence clicks. No daily-stats table exists in this implementation.'],JSON_THROW_ON_ERROR));
        }finally{$redis->close();}
        return self::SUCCESS;
    }
    private function state(int $id):array
    {
        $row=DB::table('links')->useWritePdo()->where('id',$id)->first();
        return ['counter'=>(int)$row->click_count,'archived'=>(int)$row->archived_click_count,
            'raw'=>DB::table('link_clicks')->useWritePdo()->where('link_id',$id)->count(),
            'ledger'=>DB::table('stream_processed_events')->useWritePdo()->where('link_id',$id)->where('consumer_group','analytics-writer')->count()];
    }
    private function replace(\Redis $redis,string $source,int $id):int
    {
        $count=null;
        $ok=app(ClickMaintenanceService::class)->run(function()use($redis,$source,$id,&$count){
            DB::transaction(function()use($redis,$source,$id,&$count){
                $row=DB::table('links')->where('id',$id)->lockForUpdate()->first();
                if(!$row || !str_starts_with($row->short_code,'lab7rb_') || (int)$row->archived_click_count!==0)throw new RuntimeException('Wrong fixture');
                $entries=$redis->rawCommand('XRANGE',$source,'-','+');
                if(!is_array($entries))throw new RuntimeException('Cannot read retained history');
                $events=[];
                foreach($entries as $entry){
                    $fields=$entry[1]??[];$encoded=null;
                    for($i=0;$i<count($fields);$i+=2)if($fields[$i]==='event')$encoded=$fields[$i+1];
                    if(!is_string($encoded))throw new RuntimeException('Malformed stream entry');
                    $events[]=json_decode($encoded,true,512,JSON_THROW_ON_ERROR);
                }
                $ledger=DB::table('stream_processed_events')->useWritePdo()->where('link_id',$id)
                    ->where('consumer_group','analytics-writer')->pluck('payload_hash','event_id')->all();
                $count=app(ClickReplayCoverage::class)->count($events,$ledger,$id);
                DB::table('links')->where('id',$id)->update(['click_count'=>$count]);
            },3);
        },lockWaitSeconds:1);
        if(!$ok)throw new RuntimeException('Maintenance busy; rerun drill later');
        return $count;
    }
}
