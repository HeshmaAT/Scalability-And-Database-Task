<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing')||!config('lab6.enabled'))throw new RuntimeException('Local dashboard lab only');
$db=\Illuminate\Support\Facades\DB::connection();
if(($argv[1]??'')==='sample'){
    $start=microtime(true);$deadline=$start+12;$rows=[];
    do {
        $owner=$db->selectOne('SELECT IS_USED_LOCK(?) AS owner',['analytics:partition-maintenance'],false)->owner;
        $process=$owner?$db->selectOne("SELECT ID, HOST, COMMAND, TIME, STATE, UPPER(SUBSTRING_INDEX(TRIM(INFO), ' ', 1)) AS statement_type FROM information_schema.PROCESSLIST WHERE ID = ?",[(int)$owner],false):null;
        $rows[]=['elapsed_ms'=>round((microtime(true)-$start)*1000),'owner'=>$owner===null?null:(int)$owner,'process'=>$process];
        usleep(100000);
    }while(microtime(true)<$deadline);
    $incomplete=$db->table('click_archive_runs')->useWritePdo()->where('status','!=','completed')->limit(10)->get(['id','status']);
    echo json_encode(['samples'=>$rows,'incomplete_archive_runs'=>$incomplete,'note'=>'Process TIME is current statement age; consecutive ownership samples give only a lower bound on lock hold duration. No query literals or credentials recorded.'],JSON_THROW_ON_ERROR),PHP_EOL;exit;
}
$id=(int)($argv[2]??0);
$link=\App\Models\Link::query()->useWritePdo()->findOrFail($id);
if(!str_starts_with($link->short_code,'lab6b_'))throw new RuntimeException('Not a comparison fixture');
$count=(int)$link->click_count;
$rows=$db->table('dashboard_events as d')->useWritePdo()->join('stream_processed_events as s','s.event_id','=','d.event_id')->where('d.link_id',$id)->where('s.consumer_group','analytics-writer')->orderBy('d.id')->get(['d.occurred_at','d.projected_at','s.processed_at']);
$a=[];$b=[];$utc=new DateTimeZone('UTC');
function millis($s,$utc){return (float)(new DateTimeImmutable($s,$utc))->format('U.u')*1000;}
foreach($rows as$row){$a[]=max(0,millis($row->processed_at,$utc)-millis($row->occurred_at,$utc));$b[]=max(0,millis($row->projected_at,$utc)-millis($row->processed_at,$utc));}
function stats($values){sort($values);$n=count($values);return ['samples'=>$n,'p50_ms'=>$n?round($values[max(0,(int)ceil($n*.5)-1)],3):null,'p95_ms'=>$n?round($values[max(0,(int)ceil($n*.95)-1)],3):null];}
$redis=app(\App\Services\Events\EventStream::class)->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2);$groups=[];
try{foreach(($redis->rawCommand('XINFO','GROUPS',config('events.stream'))?:[])as$fields){$group=[];for($i=0;$i<count($fields);$i+=2)$group[$fields[$i]]=$fields[$i+1];$groups[]=['name'=>$group['name']??null,'pending'=>$group['pending']??null,'lag'=>$group['lag']??null];}}finally{$redis->close();}
echo json_encode(['link_id'=>$id,'final_count'=>$count,'projected_rows'=>$rows->count(),'click_to_analytics'=>stats($a),'analytics_to_dashboard'=>stats($b),'groups'=>$groups,'timing_note'=>'Ledger processed_at is assigned inside the transaction, not a separately measured commit time; SQL timestamps may have one-second precision. Stage timings are diagnostic estimates.'],JSON_THROW_ON_ERROR),PHP_EOL;
