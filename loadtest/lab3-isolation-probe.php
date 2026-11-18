<?php

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Local/testing only.');
}
if (config('egress.proxy') !== 'http://squid:3128') {
    throw new RuntimeException('Global egress proxy is not configured.');
}

$rows = [];
foreach ([['https://example.com/', 200], ['http://127.0.0.1/', 403],
          ['http://169.254.169.254/', 403], ['http://db-primary:3306/', 403],
          ['http://private-destination.test/', 403]] as [$url, $expected]) {
    try {
        // No withOptions(proxy): this checks the application's global policy.
        $response = \Illuminate\Support\Facades\Http::get($url);
        $rows[] = ['url' => $url, 'status' => $response->status(), 'expected' => $expected,
            'passed' => $response->status() === $expected, 'proxy_error' => $response->header('X-Squid-Error')];
    } catch (\Throwable $e) {
        $rows[] = ['url' => $url, 'passed' => false, 'error' => $e->getMessage()];
    }
}
$errno = 0;
$error = '';
$socket = @stream_socket_client('tcp://1.1.1.1:443', $errno, $error, 3);
$directConnected = is_resource($socket);
if ($directConnected) {
    fclose($socket);
}
$rows[] = ['test' => 'raw TCP bypass attempt to public IP 1.1.1.1:443',
    'direct_connected' => $directConnected, 'errno' => $errno, 'error' => $error,
    'passed' => ! $directConnected];

$curl = curl_init('https://example.com/');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '',
    CURLOPT_NOPROXY => '*', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
$body = curl_exec($curl);
$rows[] = ['test' => 'raw cURL with proxy explicitly disabled', 'http_status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
    'curl_errno' => curl_errno($curl), 'error' => curl_error($curl), 'passed' => $body === false];
curl_close($curl);
echo json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
