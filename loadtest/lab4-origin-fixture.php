<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local/testing only.');
}
if (config('filesystems.disks.s3.endpoint') !== 'http://toxiproxy:19000') {
    throw new RuntimeException('S3 endpoint is not the intended Toxiproxy endpoint.');
}
$owner = \App\Models\User::query()->useWritePdo()->orderBy('id')->firstOrFail();
$link = app(\App\Services\CreateLinkService::class)->create(
    $owner, 'https://example.com/lab4-origin', 'lab4qr_'.bin2hex(random_bytes(6)),
);
\Illuminate\Support\Facades\DB::table('link_qr_codes')->insert([
    'link_id' => $link->id, 'status' => 'pending',
]);
(new \App\Jobs\GenerateQrCode($link->id))->handle();
$metadata = \Illuminate\Support\Facades\DB::table('link_qr_codes')
    ->useWritePdo()->where('link_id', $link->id)->first();
if ($metadata === null || $metadata->status !== 'ready' || ! $metadata->object_key) {
    throw new RuntimeException('QR generation did not complete.');
}
$image = \Illuminate\Support\Facades\Storage::disk('s3')->get($metadata->object_key);
$url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
    'qr.image', now()->addMinutes(20), ['linkId' => $link->id],
);
echo json_encode([
    'id' => $link->id, 'code' => $link->short_code,
    'object_key' => $metadata->object_key, 'url' => $url,
    'image_bytes' => strlen($image), 'sha256' => hash('sha256', $image),
    's3_endpoint' => config('filesystems.disks.s3.endpoint'),
], JSON_THROW_ON_ERROR);
