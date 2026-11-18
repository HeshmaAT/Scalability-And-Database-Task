<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('This probe is for local/testing only.');
}

$tests = [
    ['https://example.com/', false],
    ['http://db-primary:3306/', true],
    ['http://127.0.0.1/', true],
    ['http://169.254.169.254/', true],
    ['http://private-destination.test/', true],
];
$rows = [];
foreach ($tests as [$url, $blocked]) {
    try {
        $response = \Illuminate\Support\Facades\Http::withOptions([
            'proxy' => ['http' => 'http://squid:3128', 'https' => 'http://squid:3128', 'no' => []],
            'allow_redirects' => false,
        ])->connectTimeout(3)->timeout(8)->get($url);
        $status = $response->status();
        $rows[] = [
            'url' => $url,
            'expected_blocked' => $blocked,
            'status' => $status,
            'proxy_error' => $response->header('X-Squid-Error'),
            'passed' => $blocked ? $status === 403 : $status === 200,
        ];
    } catch (\Throwable $exception) {
        $rows[] = ['url' => $url, 'expected_blocked' => $blocked, 'passed' => false,
            'exception' => get_class($exception), 'message' => $exception->getMessage()];
    }
}
echo json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
