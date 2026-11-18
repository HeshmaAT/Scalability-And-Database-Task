<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local/testing only.');
}
$link = \App\Models\Link::query()->useWritePdo()->findOrFail((string) ($argv[1] ?? ''));
if (! str_starts_with($link->short_code, 'lab4qr_')) {
    throw new RuntimeException('Only Lab 4 fixture links allowed.');
}
$urls = [];
foreach (['eu_sample', 'asia_sample', 'eu_burst', 'asia_burst'] as $label) {
    $urls[$label] = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'qr.image', now()->addMinutes(20), [
            'linkId' => $link->id,
            'lab4_nonce' => bin2hex(random_bytes(8)),
        ],
    );
}
echo json_encode($urls, JSON_THROW_ON_ERROR);
