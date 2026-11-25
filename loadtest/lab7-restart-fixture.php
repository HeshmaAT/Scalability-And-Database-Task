<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\Link;
use App\Services\Events\EventStream;
if(!app()->environment('local','testing')||getenv('LAB7_FAILURE_DRILLS')!=='1')throw new RuntimeException('Local opt-in required');
$action=$argv[1]??'';$id=(int)($argv[2]??0);
function fixture(int $id):Link{$l=Link::query()->useWritePdo()->findOrFail($id);if(!str_starts_with($l->short_code,'lab7rs_'))throw new RuntimeException('Wrong fixture');return $l;}
function emit(array $value):void{echo json_encode($value,JSON_THROW_ON_ERROR),PHP_EOL;}
if($action==='prepare'){
    $demo=json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents('/tmp/lab7-demo-private.json')),true,512,JSON_THROW_ON_ERROR);
    $owner=\App\Models\User::query()->useWritePdo()->where('email',$demo['email'])->firstOrFail();
    $suffix=bin2hex(random_bytes(6));$create=app(\App\Services\CreateLinkService::class);
    $control=$create->create($owner,'https://example.com/control','lab7rs_'.$suffix.'_control');
    $live=$create->create($owner,'https://example.com/live','lab7rs_'.$suffix.'_live');
    emit(['link_id'=>$live->id,'short_code'=>$live->short_code,'code'=>$live->short_code,'control_id'=>$control->id,'stream'=>config('events.stream')]);exit;
}
$link=fixture($id);
if($action==='notice'){
    $eventId=(string)Str::uuid();$now=now('UTC');
    DB::transaction(function()use($link,$eventId,$now){
        // A control-link state change and notification intent commit without Redis.
        $fresh=Link::query()->useWritePdo()->findOrFail($link->id);
        app(\App\Services\Events\LinkLifecycle::class)->change($fresh,['is_active'=>!$fresh->is_active],(int)$fresh->lifecycle_version);
        $event=['event_id'=>$eventId,'event_type'=>'MilestoneReached','schema_version'=>1,
            'occurred_at'=>$now->format('Y-m-d\TH:i:s.u\Z'),'ordering_key'=>(string)$link->id,
            'payload'=>['link_id'=>$link->id,'milestone'=>1000,'cumulative_count'=>1000,'lab7_synthetic_notice'=>true]];
        DB::table('event_outbox')->insert(['event_id'=>$eventId,'event_type'=>'MilestoneReached','stream_name'=>config('events.stream'),
            'envelope'=>json_encode($event,JSON_THROW_ON_ERROR),'created_at'=>$now,'published_at'=>null]);
    });
    emit(['event_id'=>$eventId,'committed_at'=>microtime(true),'published'=>false]);exit;
}
if($action==='outbox'){
    $eventId=$argv[3]??'';$row=DB::table('event_outbox')->useWritePdo()->where('event_id',$eventId)->first();
    if(!$row||json_decode($row->envelope,true)['payload']['link_id']!==$link->id)throw new RuntimeException('Wrong notice');
    emit(['published'=>$row->published_at!==null]);exit;
}
if($action==='deliver'){
    $eventId=$argv[3]??'';$outbox=DB::table('event_outbox')->useWritePdo()->where('event_id',$eventId)->first();
    if(!$outbox||!$outbox->published_at||json_decode($outbox->envelope,true)['payload']['link_id']!==$link->id)throw new RuntimeException('Notice was not published');
    $secret=trim(file_get_contents('/tmp/lab7-webhook-secret'));
    config(['webhooks.test_receiver'=>true,'webhooks.proxy'=>'http://lab5-webhook-proxy:3128']);
    // Keep this diagnostic row locked until delivery finishes. The ordinary worker
    // skips it; its public-only proxy policy is unchanged.
    $delivery=DB::transaction(function()use($outbox,$link,$secret,$eventId){
        $now=now('UTC');
        DB::table('webhook_deliveries')->insertOrIgnore(['event_id'=>$eventId,'link_id'=>$link->id,'url'=>'http://lab5-webhook-receiver/ok',
            'secret'=>Crypt::encryptString($secret),'body'=>$outbox->envelope,'status'=>'pending','attempts'=>0,
            'available_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('webhook_deliveries')->where('event_id',$eventId)->lockForUpdate()->first();
        return app(\App\Services\Events\WebhookDeliveryWorker::class)->tick($eventId);
    });
    emit(['delivery'=>$delivery,'final_status'=>DB::table('webhook_deliveries')->useWritePdo()->where('event_id',$eventId)->value('status')]);exit;
}
if($action==='state'){
    $raw=DB::table('link_clicks')->useWritePdo()->where('link_id',$id)->count();
    $ledger=DB::table('stream_processed_events')->useWritePdo()->where('link_id',$id)->where('consumer_group','analytics-writer')->count();
    $dashboard=DB::table('dashboard_events')->useWritePdo()->where('link_id',$id)->count();
    $redis=app(EventStream::class)->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2);
    try{
        $groups=$redis->rawCommand('XINFO','GROUPS',config('events.stream'));
        $pending=$redis->rawCommand('XPENDING',config('events.stream'),'dashboard','-','+','5');$details=[];
        foreach($pending?:[] as $entry){
            $rows=$redis->rawCommand('XRANGE',config('events.stream'),$entry[0],$entry[0]);$event=null;
            foreach($rows?:[]as$row){$fields=$row[1];for($j=0;$j<count($fields);$j+=2)if($fields[$j]==='event')$event=json_decode($fields[$j+1],true);}
            $uuid=$event['event_id']??null;
            $details[]=['stream_id'=>$entry[0],'idle_ms'=>$entry[2]??null,'delivery_count'=>$entry[3]??null,
                'event_id'=>$uuid,'type'=>$event['event_type']??null,'link_id'=>$event['payload']['link_id']??null,
                'analytics_committed'=>$uuid?DB::table('stream_processed_events')->useWritePdo()->where('consumer_group','analytics-writer')->where('event_id',$uuid)->exists():false,
                'dashboard_stored'=>$uuid?DB::table('dashboard_events')->useWritePdo()->where('event_id',$uuid)->exists():false];
        }
    }finally{$redis->close();}
    emit(['count'=>(int)$link->click_count,'raw'=>$raw,'ledger'=>$ledger,'dashboard_rows'=>$dashboard,'groups'=>$groups,'pending_dashboard_sample'=>$details]);exit;
}
throw new RuntimeException('Unknown action');
