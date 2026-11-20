<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local','testing')) throw new RuntimeException('Lab only');
$action=$argv[1];
if ($action==='create') {
    $owner=\App\Models\User::query()->useWritePdo()->firstOrFail();
    $link=\App\Models\Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab5an_'.bin2hex(random_bytes(6)),'destination_url'=>'https://example.com/lab5','is_active'=>true]);
    $key='lab5:test:'.bin2hex(random_bytes(8));config(['events.stream'=>$key]);
    $stream=app(\App\Services\Events\EventStream::class);$stream->initialize();
    for ($i=0;$i<400;$i++) $stream->recordClick($link->id,'203.0.113.10');
    $redis=$stream->client();
    if ($redis->rawCommand('XLEN',$key)!==400) throw new RuntimeException('Fixture publication incomplete');
    $first=$redis->rawCommand('XRANGE',$key,'-','+','COUNT','1');
    echo json_encode(['link_id'=>$link->id,'stream'=>$key,'first'=>$first[0][1],'code'=>$link->short_code]);
} else {
    $link=\App\Models\Link::query()->useWritePdo()->findOrFail((int)$argv[2]);
    if (!str_starts_with($link->short_code,'lab5an_')) throw new RuntimeException('Wrong fixture');
    echo json_encode(['count'=>(int)$link->click_count,'raw'=>\Illuminate\Support\Facades\DB::table('link_clicks')->useWritePdo()->where('link_id',$link->id)->count(),
    'consumers'=>\Illuminate\Support\Facades\DB::table('stream_processed_events')->useWritePdo()->where('link_id',$link->id)->selectRaw('consumer_name, COUNT(*) as total')->groupBy('consumer_name')->get()]);
}
