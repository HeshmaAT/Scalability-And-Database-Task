<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
if ($action === 'prepare') {
    $owner = \App\Models\User::query()->useWritePdo()->orderBy('id')->firstOrFail();
    $service = app(\App\Services\CreateLinkService::class);
    $links = [];
    foreach (['clicks', 'pause'] as $purpose) {
        $code = 'lab3mc_'.bin2hex(random_bytes(6));
        $link = $service->create($owner, 'https://example.com/lab3-microcache', $code);
        $links[$purpose] = ['id' => $link->id, 'code' => $link->short_code];
    }
    echo json_encode($links, JSON_THROW_ON_ERROR);
    exit;
}

$id = (string) ($argv[2] ?? '');
if (! preg_match('/\A[1-9][0-9]*\z/', $id)) {
    throw new RuntimeException('Invalid fixture ID.');
}
$link = \App\Models\Link::query()->useWritePdo()->findOrFail($id);
if (! str_starts_with($link->short_code, 'lab3mc_')) {
    throw new RuntimeException('Only isolated micro-cache fixture links may be changed.');
}
if ($action === 'pause' || $action === 'resume') {
    $owner = \App\Models\User::query()->useWritePdo()->findOrFail($link->user_id);
    app(\App\Services\UpdateLinkService::class)->update($owner, $id, ['is_active' => $action === 'resume']);
    echo json_encode(['action' => $action, 'service_returned_at_unix' => microtime(true)], JSON_THROW_ON_ERROR);
} elseif ($action === 'stats') {
    echo json_encode([
        'click_count' => (int) $link->click_count,
        'raw_clicks' => \Illuminate\Support\Facades\DB::table('link_clicks')->useWritePdo()->where('link_id', $id)->count(),
    ], JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Unknown fixture action.');
}
