<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local','testing')||!config('lab6.enabled'))throw new RuntimeException('Local dashboard lab only');
$owner=\App\Models\User::query()->useWritePdo()->where('email',$argv[1]??'')->firstOrFail();
$link=\App\Models\Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab6b_'.bin2hex(random_bytes(6)),'destination_url'=>'https://example.com/lab6-benchmark','is_active'=>true]);
app(\App\Services\LinkCacheService::class)->refresh($link->short_code);
echo json_encode(['link_id'=>$link->id,'short_code'=>$link->short_code],JSON_THROW_ON_ERROR),PHP_EOL;
