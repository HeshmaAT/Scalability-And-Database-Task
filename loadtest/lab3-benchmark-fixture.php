<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'queue-state') {
    echo json_encode([
        'pending' => \Illuminate\Support\Facades\Redis::connection('queue')->llen('analytics:pending'),
        'processing' => \Illuminate\Support\Facades\Redis::connection('queue')->hlen('analytics:processing'),
        'queued_jobs' => \Illuminate\Support\Facades\Queue::connection('redis')->size('default'),
    ], JSON_THROW_ON_ERROR);
    exit;
}
if (($argv[1] ?? '') !== 'prepare') {
    throw new RuntimeException('Unknown command.');
}
$owner = \App\Models\User::query()->useWritePdo()->orderBy('id')->firstOrFail();
$run = bin2hex(random_bytes(4));
$codes = [];
$rows = [];
for ($i = 0; $i < 1000; $i++) {
    $code = 'lab3mc_'.$run.base_convert((string) $i, 10, 36);
    $codes[] = $code;
    $rows[] = [
        'user_id' => $owner->id,
        'destination_url' => 'https://example.com/lab3-benchmark',
        'short_code' => $code,
        'is_active' => true,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ];
}
\Illuminate\Support\Facades\DB::table('links')->insert($rows);
$cache = app(\App\Services\LinkCacheService::class);
$links = \App\Models\Link::query()->useWritePdo()->whereIn('short_code', array_slice($codes, 0, 10))->get();
foreach ($links as $link) {
    $cache->put($link);
}
$token = $owner->createToken('lab3-micro-benchmark', ['*'], now()->addHours(2));
$tokenId = $token->accessToken->id;
// Clear sticky state, then check token visibility on the configured read connection.
\Illuminate\Support\Facades\DB::connection()->forgetRecordModificationState();
$deadline = microtime(true) + 45;
while (! \Illuminate\Support\Facades\DB::table('personal_access_tokens')->where('id', $tokenId)->exists()) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Token is not visible to the read connection; replication not ready.');
    }
    usleep(250000);
}
echo json_encode(['codes' => $codes, 'user_id' => $owner->id, 'token' => $token->plainTextToken], JSON_THROW_ON_ERROR);
