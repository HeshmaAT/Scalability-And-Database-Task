<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing')||!config('lab6.enabled'))throw new RuntimeException('Local lab only');
$id=(int)($argv[1]??0);$minimum=(int)($argv[2]??0);
$link=\App\Models\Link::query()->useWritePdo()->findOrFail($id);
if(!str_starts_with($link->short_code,'lab6b_'))throw new RuntimeException('Not a comparison fixture');
$deadline=microtime(true)+60;$stable=0;$previous=-1;$rows=collect();
do {
    $count=(int)\App\Models\Link::query()->useWritePdo()->whereKey($id)->value('click_count');
    $rows=\Illuminate\Support\Facades\DB::table('dashboard_events')->useWritePdo()->where('link_id',$id)->orderBy('id')->get();
    $stable=($count===$previous && $rows->count()===$count && $count>=$minimum)?$stable+1:0;
    $previous=$count;
    if($stable>=3)break;
    usleep(1000000);
}while(microtime(true)<$deadline);
$projection=app(\App\Services\Dashboard\DashboardProjection::class);
echo json_encode(['link_id'=>$id,'snapshot'=>['total_clicks'=>$count],
    'events'=>$rows->map(fn($row)=>$projection->payload($row))->values(),
    'history_complete'=>$rows->count()===$count && $count>=$minimum,
    'minimum_successful_redirects'=>$minimum,'projection_rows'=>$rows->count(),
    'method'=>'Primary database read through PHP CLI; independent of busy web PHP-FPM workers'],JSON_THROW_ON_ERROR),PHP_EOL;
