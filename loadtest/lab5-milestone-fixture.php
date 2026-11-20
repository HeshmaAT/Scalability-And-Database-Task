<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing'))throw new RuntimeException('Lab only');
$action=$argv[1];
if($action==='create'){
    $owner=\App\Models\User::query()->useWritePdo()->firstOrFail();
    $link=\App\Models\Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab5ms_'.bin2hex(random_bytes(6)),'destination_url'=>'https://example.com/milestone','is_active'=>true]);
    $key='lab5:test:ms:'.bin2hex(random_bytes(8));config(['events.stream'=>$key]);$stream=app(\App\Services\Events\EventStream::class);$stream->initialize();
    for($i=0;$i<1001;$i++)if(!$stream->recordClick($link->id,'203.0.113.15'))throw new RuntimeException('Fixture append failed');
    $redis=$stream->client();$first=$redis->rawCommand('XRANGE',$key,'-','+','COUNT','1');
    echo json_encode(['id'=>$link->id,'stream'=>$key,'first'=>$first[0][1]]);
}else{
    $id=(int)$argv[2];$link=\App\Models\Link::query()->useWritePdo()->findOrFail($id);if(!str_starts_with($link->short_code,'lab5ms_'))throw new RuntimeException('Wrong fixture');
    $db=\Illuminate\Support\Facades\DB::class;
    $milestones=$db::table('link_milestones')->useWritePdo()->where('link_id',$id)->get();
    echo json_encode(['projection_count'=>(int)$db::table('stream_milestone_counters')->useWritePdo()->where('link_id',$id)->value('count'),'milestones'=>$milestones,
        'processed_events'=>$db::table('stream_processed_events')->useWritePdo()->where('consumer_group','milestones')->where('link_id',$id)->count(),
        'outbox'=>$db::table('event_outbox')->useWritePdo()->whereIn('event_id',$milestones->pluck('event_id'))->get()]);
}
