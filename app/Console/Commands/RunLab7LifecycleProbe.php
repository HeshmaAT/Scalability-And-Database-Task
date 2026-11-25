<?php
namespace App\Console\Commands;
use App\Models\Link;
use App\Models\User;
use App\Services\CreateLinkService;
use App\Services\UpdateLinkService;
use App\Services\Events\EventStream;
use App\Services\Events\LinkLifecycle;
use App\Services\Events\StreamFailures;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RunLab7LifecycleProbe extends Command
{
    protected $signature='lab7:lifecycle-probe {action} {--stream=} {--link=} {--expected-version=} {--active=} {--run=} {--count=1000} {--start-at=0}';
    protected $description='Opt-in isolated Lab 7 lifecycle failure and replay fixtures';
    public function handle(LinkLifecycle $lifecycle):int {
        $key=(string)$this->option('stream');$run=(string)$this->option('run');
        if(!app()->environment('local','testing')||getenv('LAB7_FAILURE_DRILLS')!=='1'||!str_starts_with($key,'lab5:test:lab7:')||!preg_match('/^[a-f0-9]{8,16}$/',$run))throw new \RuntimeException('Explicit isolated Lab 7 lifecycle probe required');
        config(['events.stream'=>$key]);$action=$this->argument('action');
        $owner=User::query()->useWritePdo()->firstOrFail();
        $make=function(string $suffix)use($owner,$run):Link {
            return app(CreateLinkService::class)->create($owner,'https://example.com/lifecycle/'.$suffix,'lab7lc_'.$run.'_'.$suffix);
        };
        if($action==='naive-lost'){
            $link=DB::transaction(fn()=>Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab7lc_'.$run.'_lost','destination_url'=>'https://example.com/lost','is_active'=>true]));
            $this->line(json_encode(['lab7_fault_reached'=>'naive-commit-before-publish','link_id'=>$link->id]));if(defined('STDOUT'))fflush(STDOUT);while(true)usleep(100000);
        }
        if($action==='naive-phantom'){
            DB::beginTransaction();
            try{
                $link=Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab7lc_'.$run.'_phantom','destination_url'=>'https://example.com/phantom','is_active'=>true]);
                $envelope=['event_id'=>(string)Str::uuid(),'event_type'=>'LinkCreated','occurred_at'=>now('UTC')->toISOString(),'schema_version'=>1,'ordering_key'=>(string)$link->id,'payload'=>['link_id'=>$link->id,'version'=>1,'changes'=>$lifecycle->state($link),'state'=>$lifecycle->state($link)]];
                $redis=app(EventStream::class)->client();try{$redis->rawCommand('XADD',$key,'*','event',json_encode($envelope,JSON_THROW_ON_ERROR));}finally{$redis->close();}
                $id=$link->id;
            }finally{DB::rollBack();}
            $this->line(json_encode(['link_id'=>$id,'row_exists'=>Link::query()->useWritePdo()->whereKey($id)->exists(),'published_event_id'=>$envelope['event_id']]));return 0;
        }
        if($action==='fixed-rollback'){
            DB::beginTransaction();
            try{$link=$make('rollback');$id=$link->id;$uuid=DB::table('link_events')->useWritePdo()->where('link_id',$id)->value('event_id');}finally{DB::rollBack();}
            $this->line(json_encode(['link_id'=>$id,'row_exists'=>Link::query()->useWritePdo()->whereKey($id)->exists(),'event_rows'=>DB::table('link_events')->useWritePdo()->where('link_id',$id)->count(),'outbox_rows'=>DB::table('event_outbox')->useWritePdo()->where('event_id',$uuid)->count()]));return 0;
        }
        if($action==='fixed-commit'){
            $link=$make('fixed');
            $this->line(json_encode(['lab7_fault_reached'=>'fixed-commit-before-relay','link_id'=>$link->id]));if(defined('STDOUT'))fflush(STDOUT);while(true)usleep(100000);
        }
        if($action==='create'){
            $link=$make('cas');$this->line(json_encode(['link_id'=>$link->id,'version'=>(int)$link->lifecycle_version]));return 0;
        }
        if($action==='mutate'){
            $id=(int)$this->option('link');$expected=(int)$this->option('expected-version');$active=$this->option('active')==='1';
            $this->fixture($id,$run);
            while(microtime(true)*1000<(float)$this->option('start-at'))usleep(1000);
            try{
                $link=DB::transaction(function()use($lifecycle,$id,$expected,$active){
                    $link=Link::query()->useWritePdo()->findOrFail($id);
                    $lifecycle->change($link,['is_active'=>$active],$expected);return $link;
                });
                app(\App\Services\LinkCacheService::class)->refresh($link->short_code);
                $this->line(json_encode(['status'=>'won','version'=>(int)$link->lifecycle_version,'active'=>$active]));
            }catch(ConflictHttpException){$this->line(json_encode(['status'=>'rejected','http_status'=>409]));}
            return 0;
        }
        if($action==='seed'){
            $count=(int)$this->option('count');if($count<1||$count>1000)throw new \RuntimeException('Fixture count out of range');
            $started=hrtime(true);
            for($i=0;$i<$count;$i++){
                $link=app(CreateLinkService::class)->create($owner,'https://example.com/lifecycle/'.$i,'lab7es_'.$run.'_'.$i,$i%2===0?'2030-01-01T00:00:00Z':null);
                if($i%2===1)$link=app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>false,'expected_version'=>(int)$link->lifecycle_version]);
                if($i%3===0)$link=app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>true,'expected_version'=>(int)$link->lifecycle_version]);
                if($i%5===0)app(UpdateLinkService::class)->update($owner,(string)$link->id,['expires_at'=>'2031-01-01T00:00:00Z','expected_version'=>(int)$link->lifecycle_version]);
            }
            $this->line(json_encode(['links'=>$count,'seed_seconds'=>(hrtime(true)-$started)/1e9]));return 0;
        }
        if($action==='rebuild'){
            $links=Link::query()->useWritePdo()->where('short_code','like','lab7es_'.$run.'_%')->orderBy('id')->get();
            $ids=$links->pluck('id')->map(fn($id)=>(int)$id)->all();$started=hrtime(true);$states=$lifecycle->replay($ids);$seconds=(hrtime(true)-$started)/1e9;
            foreach($links as$link){$state=$states[(int)$link->id];if($state['state']!==$lifecycle->state($link)||$state['version']!==(int)$link->lifecycle_version)throw new \RuntimeException('Rebuilt state differs from links row');}
            $this->line(json_encode(['links'=>$links->count(),'events'=>DB::table('link_events')->useWritePdo()->whereIn('link_id',$ids)->count(),'rebuild_seconds'=>$seconds,'all_states_match'=>true,'method'=>'Read append-only MariaDB lifecycle records in version order; apply event changes into detached state; compare destination, active flag, expiry and version to links. No Redis stream history required.']));return 0;
        }
        if($action==='inspect'){
            $link=$this->fixture((int)$this->option('link'),$run);
            $this->line(json_encode(['link_id'=>$link->id,'version'=>(int)$link->lifecycle_version,'state'=>$lifecycle->state($link),'events'=>DB::table('link_events')->useWritePdo()->where('link_id',$link->id)->orderBy('version')->get(['event_id','version','type']),'outbox_rows'=>DB::table('event_outbox')->useWritePdo()->where('stream_name',$key)->count(),'unpublished'=>DB::table('event_outbox')->useWritePdo()->where('stream_name',$key)->whereNull('published_at')->count()]));return 0;
        }
        if($action==='summary'){
            $rows=DB::table('event_outbox')->useWritePdo()->where('stream_name',$key)->get(['event_id','published_at','envelope']);
            $redis=app(EventStream::class)->client();try{$entries=$redis->rawCommand('XRANGE',$key,'-','+','COUNT','100000')?:[];}finally{$redis->close();}
            $ids=[];$payloadMismatches=0;$expectedPayloads=$rows->keyBy('event_id');foreach($entries as[$entryId,$fields]){$map=StreamFailures::fields($fields);$event=json_decode($map['event']??'',true,512,JSON_THROW_ON_ERROR);$ids[]=$event['event_id'];if(isset($expectedPayloads[$event['event_id']])&&json_decode($expectedPayloads[$event['event_id']]->envelope,true)!==$event)$payloadMismatches++;}
            $expected=$rows->pluck('event_id')->all();$missing=array_values(array_diff($expected,$ids));$phantom=array_values(array_diff($ids,$expected));
            $this->line(json_encode(['outbox_rows'=>$rows->count(),'unpublished'=>$rows->whereNull('published_at')->count(),'stream_entries'=>count($ids),'unique_events'=>count(array_unique($ids)),'duplicate_deliveries'=>count($ids)-count(array_unique($ids)),'missing_events'=>count($missing),'phantom_events'=>count(array_unique($phantom)),'payload_mismatches'=>$payloadMismatches,'expected_event_ids'=>$expected]));return 0;
        }
        throw new \RuntimeException('Unknown lifecycle probe action');
    }
    private function fixture(int $id,string $run):Link {
        $link=Link::query()->useWritePdo()->findOrFail($id);
        if(!str_starts_with($link->short_code,'lab7lc_'.$run.'_')&&!str_starts_with($link->short_code,'lab7es_'.$run.'_'))throw new \RuntimeException('Not a lifecycle fixture');return $link;
    }
}
