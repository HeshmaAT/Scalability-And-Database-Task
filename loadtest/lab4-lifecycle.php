<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local', 'testing') || !config('lab4.enabled')) throw new RuntimeException('Lab only');
$action = $argv[1] ?? '';
if ($action === 'create') {
    $owner = \App\Models\User::query()->useWritePdo()->firstOrFail();
    $link = \App\Models\Link::forceCreate(['user_id'=>$owner->id, 'short_code'=>'lab4life_'.bin2hex(random_bytes(6)), 'destination_url'=>'https://example.com/lifecycle', 'is_active'=>true]);
    \Illuminate\Support\Facades\DB::table('link_qr_codes')->insert(['link_id'=>$link->id,'status'=>'pending']);
} else {
    $link = \App\Models\Link::query()->useWritePdo()->findOrFail((int)($argv[2] ?? 0));
    if (!str_starts_with($link->short_code, 'lab4life_')) throw new RuntimeException('Not a lifecycle fixture');
}
if ($action === 'delete') {
    $link->delete();
    echo json_encode(['id'=>$link->id,'deleted'=>true]);
    exit;
}
if ($action === 'regenerate') {
    \Illuminate\Support\Facades\DB::table('link_qr_codes')->where('link_id',$link->id)->update(['status'=>'pending']);
}
if (!in_array($action,['create','regenerate'],true)) throw new RuntimeException('Unknown action');
(new \App\Jobs\GenerateQrCode($link->id))->handle();
$meta = \Illuminate\Support\Facades\DB::table('link_qr_codes')->useWritePdo()->where('link_id',$link->id)->first();
if ($meta->status !== 'ready') throw new RuntimeException('QR not ready');
echo json_encode(['id'=>$link->id,'key'=>$meta->object_key,'sha256'=>hash('sha256',\Illuminate\Support\Facades\Storage::disk('s3')->get($meta->object_key)), 'url'=>\Illuminate\Support\Facades\URL::temporarySignedRoute('qr.image',now()->addMinutes(10),['linkId'=>$link->id])]);
