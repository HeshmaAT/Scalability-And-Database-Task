<?php

namespace Tests\Feature;

use App\Http\Controllers\Lab\Lab3ClientInfoController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyTrustTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['lab3.enabled' => true]);
        Route::get('/__lab3/trust', Lab3ClientInfoController::class);
    }

    public function test_trusted_nginx_supplies_client_ip_and_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.30.53.10', 'HTTPS' => 'off'])
            ->getJson('/__lab3/trust', [
                'X-Forwarded-For' => '172.30.53.20',
                'X-Forwarded-Proto' => 'https',
            ])
            ->assertOk()
            ->assertJsonPath('client_ip', '172.30.53.20')
            ->assertJsonPath('is_secure', true);
    }

    public function test_untrusted_peer_cannot_override_ip_or_scheme(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTPS' => 'off'])
            ->getJson('/__lab3/trust', [
                'X-Forwarded-For' => '198.51.100.77',
                'X-Forwarded-Proto' => 'https',
            ])
            ->assertOk()
            ->assertJsonPath('client_ip', '203.0.113.9')
            ->assertJsonPath('is_secure', false);
    }

    public function test_forwarded_host_is_not_trusted(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '172.30.53.10', 'HTTPS' => 'off', 'HTTP_HOST' => 'go.brand.test',
        ])->getJson('http://go.brand.test/__lab3/trust', [
            'Host' => 'go.brand.test',
            'X-Forwarded-For' => '172.30.53.20',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example',
        ])->assertOk()->assertJsonPath('host', 'go.brand.test');
    }
}
