<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing'))throw new RuntimeException('Local lab only');
$action=$argv[1]??'';
if($action==='create'){
    $mode=$argv[2]??'';$amount=(int)($argv[3]??100);
    if(!in_array($mode,['live','after-read','after-commit','poison'],true)||$amount<1||$amount>500)throw new RuntimeException('Invalid fixture');
    $owner=\App\Models\User::query()->useWritePdo()->firstOrFail();
    $link=\App\Models\Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab7_'.bin2hex(random_bytes(7)),'destination_url'=>'https://example.com/lab7','is_active'=>true]);
    app(\App\Services\LinkCacheService::class)->refresh($link->short_code);
    $key=$mode==='live'?config('events.stream'):'lab5:test:lab7:'.$mode.':'.bin2hex(random_bytes(6));
    $redis=app(\App\Services\Events\EventStream::class)->client();$result=['link_id'=>$link->id,'code'=>$link->short_code,'stream'=>$key];
    try{
        if($mode!=='live'){
            $redis->rawCommand('XGROUP','CREATE',$key,'analytics-writer','0','MKSTREAM');
            for($i=0;$i<$amount;$i++){
                $event=['event_id'=>(string)\Illuminate\Support\Str::uuid(),'event_type'=>'LinkClicked','occurred_at'=>now('UTC')->format('Y-m-d\TH:i:s.u\Z'),'schema_version'=>1,'ordering_key'=>(string)$link->id,
                    'payload'=>['link_id'=>$link->id,'clicked_at'=>now('UTC')->format('Y-m-d H:i:s'),'visitor_hash'=>hash('sha256','lab7-fixture')]];
                if($mode==='poison'&&$i===0){$result['corrected']=$event;$event['payload']['visitor_hash']='producer-bug';$result['poison_event_id']=$event['event_id'];}
                $id=$redis->rawCommand('XADD',$key,'*','event',json_encode($event,JSON_THROW_ON_ERROR));
                if(!is_string($id))throw new RuntimeException('Fixture append failed');
                if($mode==='poison'&&$i===0)$result['poison_source_id']=$id;
            }
            $result['sent']=$amount;
        }
    }finally{$redis->close();}
    echo json_encode($result,JSON_THROW_ON_ERROR),PHP_EOL;exit;
}
if($action==='state'){
    $id=(int)($argv[2]??0);$key=$argv[3]??'';
    if($key!==config('events.stream')&&!str_starts_with($key,'lab5:test:lab7:'))throw new RuntimeException('Wrong fixture stream');
    $link=\App\Models\Link::query()->useWritePdo()->findOrFail($id);
    if(!str_starts_with($link->short_code,'lab7_'))throw new RuntimeException('Wrong fixture link');
    $redis=app(\App\Services\Events\EventStream::class)->client();$redis->setOption(\Redis::OPT_READ_TIMEOUT,2);
    try{
        $pending=$redis->rawCommand('XPENDING',$key,'analytics-writer');
        $detail=$redis->rawCommand('XPENDING',$key,'analytics-writer','-','+','10');
        $retry=$redis->rawCommand('HGET',\App\Services\Events\StreamFailures::retryKey('analytics-writer',$key),$argv[4]??'');
    }finally{$redis->close();}
    echo json_encode(['count'=>(int)$link->click_count,'raw'=>\Illuminate\Support\Facades\DB::table('link_clicks')->useWritePdo()->where('link_id',$id)->count(),
        'pending'=>(int)($pending[0]??0),'pending_detail'=>$detail?:[],'retry'=>$retry?json_decode($retry,true):null,
        'consumers'=>\Illuminate\Support\Facades\DB::table('stream_processed_events')->useWritePdo()->where('link_id',$id)->where('consumer_group','analytics-writer')->selectRaw('consumer_name, COUNT(*) as total')->groupBy('consumer_name')->get()],JSON_THROW_ON_ERROR),PHP_EOL;exit;
}
throw new RuntimeException('Unknown fixture action');
