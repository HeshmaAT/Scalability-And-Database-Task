<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local/testing only.');
}
if (config('filesystems.disks.s3.endpoint') !== 'http://toxiproxy:19000') {
    throw new RuntimeException('Wrong S3 endpoint.');
}
$owner = \App\Models\User::query()->useWritePdo()->orderBy('id')->firstOrFail();
$run = bin2hex(random_bytes(5));
$codes = [];
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $codes[] = $code = 'lab4qr_'.$run.base_convert((string) $i, 10, 36);
    $rows[] = ['user_id' => $owner->id, 'short_code' => $code,
        'destination_url' => 'https://example.com/lab4-signature', 'is_active' => true,
        'created_at' => now('UTC'), 'updated_at' => now('UTC')];
}
\Illuminate\Support\Facades\DB::table('links')->insert($rows);
$links = \App\Models\Link::query()->useWritePdo()->whereIn('short_code', $codes)->orderBy('id')->get();
\Illuminate\Support\Facades\DB::table('link_qr_codes')->insert($links->map(fn ($link) => [
    'link_id' => $link->id, 'status' => 'pending',
])->all());
$images = [];
foreach ($links as $link) {
    (new \App\Jobs\GenerateQrCode($link->id))->handle();
    $metadata = \Illuminate\Support\Facades\DB::table('link_qr_codes')->useWritePdo()->where('link_id', $link->id)->first();
    if ($metadata->status !== 'ready') {
        throw new RuntimeException('QR fixture not ready.');
    }
    $body = \Illuminate\Support\Facades\Storage::disk('s3')->get($metadata->object_key);
    $images[] = ['id' => $link->id, 'sha256' => hash('sha256', $body)];
}
$requests = [];
for ($i = 0; $i < 1000; $i++) {
    $image = $images[$i % count($images)];
    $requests[] = [
        'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('qr.image', now()->addMinutes(20), [
            'linkId' => $image['id'], 'lab4_nonce' => bin2hex(random_bytes(8)),
        ]),
        'sha256' => $image['sha256'],
    ];
}
$expired = \Illuminate\Support\Facades\URL::temporarySignedRoute(
    'qr.image', now()->subMinute(), ['linkId' => $images[0]['id']],
);
echo json_encode([
    'images' => $images, 'requests' => $requests, 'expired' => $expired,
    'api_delivery_url' => app(\App\Services\QrDeliveryUrlService::class)->forLink($images[0]['id']),
], JSON_THROW_ON_ERROR);
