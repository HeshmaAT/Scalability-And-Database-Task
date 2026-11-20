<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing'))throw new RuntimeException('Lab only');
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Crypt;use Illuminate\Support\Str;
$action=$argv[1];
config(['webhooks.test_receiver'=>true,'webhooks.proxy'=>'http://lab5-webhook-proxy:3128']);
if($action==='prepare'){
    $secret=trim(file_get_contents('/tmp/lab5-webhook-secret'));$owner=\App\Models\User::query()->useWritePdo()->firstOrFail();$result=[];
    foreach(['ok','flaky','always-fail','lost-ack']as$path){
        $link=\App\Models\Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab5wh_'.bin2hex(random_bytes(6)),'destination_url'=>'https://example.com/webhook','is_active'=>true]);
        DB::table('link_webhooks')->insert(['link_id'=>$link->id,'url'=>'http://lab5-webhook-receiver/'.$path,'secret'=>Crypt::encryptString($secret),'enabled'=>true,'created_at'=>now('UTC'),'updated_at'=>now('UTC')]);
        $key='lab5:test:wh:'.bin2hex(random_bytes(6));$eventId=(string)Str::uuid();config(['events.stream'=>$key]);$stream=app(\App\Services\Events\EventStream::class);
        $stream->append('MilestoneReached',$link->id,['milestone'=>1000,'cumulative_count'=>1000],$eventId);
        $result[$path]=['event_id'=>$eventId,'stream'=>$key];
    }echo json_encode($result);
}elseif($action==='tick'){
    $id=$argv[2];$row=DB::table('webhook_deliveries')->useWritePdo()->where('event_id',$id)->first();
    $link=\App\Models\Link::query()->useWritePdo()->findOrFail($row->link_id);if(!str_starts_with($link->short_code,'lab5wh_'))throw new RuntimeException('Wrong fixture');
    // Advance only this fixture's due time; do not sleep through real backoff in the test.
    DB::table('webhook_deliveries')->where('event_id',$id)->update(['available_at'=>now('UTC')]);
    echo json_encode(app(\App\Services\Events\WebhookDeliveryWorker::class)->tick($id));
}elseif($action==='reset-delivered'){
    $id=$argv[2];$row=DB::table('webhook_deliveries')->useWritePdo()->where('event_id',$id)->first();$link=\App\Models\Link::query()->useWritePdo()->findOrFail($row->link_id);if(!str_starts_with($link->short_code,'lab5wh_'))throw new RuntimeException('Wrong fixture');
    DB::table('webhook_deliveries')->where('event_id',$id)->update(['status'=>'retry','available_at'=>now('UTC')]);echo '{}';
}elseif($action==='security'){
    $transport=app(\App\Services\Events\WebhookTransport::class);$results=[];
    foreach(['https://127.0.0.1/','https://169.254.169.254/','https://db-primary/','http://example.com/']as$url){try{$transport->validateUrl($url);$results[$url]='ALLOWED';}catch(\Throwable){$results[$url]='rejected';}}
    echo json_encode($results);
}
