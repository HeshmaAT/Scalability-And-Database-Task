<?php

namespace Tests\Feature;

use App\Http\Controllers\Lab\Lab4QrAuthorizeController;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Lab4QrAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lab4.enabled' => true]);
        Route::get('/__lab4/auth', Lab4QrAuthorizeController::class);
        Storage::shouldReceive('disk')->never();
    }

    private function link(): Link
    {
        $link = Link::forceCreate([
            'user_id' => User::factory()->create()->id,
            'short_code' => 'lab4qr_'.bin2hex(random_bytes(6)),
            'destination_url' => 'https://example.com/test',
            'is_active' => true,
        ]);
        DB::table('link_qr_codes')->insert([
            'link_id' => $link->id, 'status' => 'ready',
            'object_key' => 'qr/test/'.bin2hex(random_bytes(8)).'.svg',
            'generated_at' => now('UTC'),
        ]);
        return $link;
    }

    private function uri(Link $link, bool $expired = false): string
    {
        $url = URL::temporarySignedRoute('qr.image', $expired ? now()->subMinute() : now()->addMinutes(5), ['linkId' => $link->id]);
        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_valid_signature_returns_cache_version_without_s3_read(): void
    {
        $this->getJson('/__lab4/auth', ['X-Lab4-Original-URI' => $this->uri($this->link())])
            ->assertNoContent()->assertHeader('X-Lab4-Cache-Version');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $uri = preg_replace('/signature=[a-f0-9]+/', 'signature=invalid', $this->uri($this->link()));
        $this->getJson('/__lab4/auth', ['X-Lab4-Original-URI' => $uri])->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $this->getJson('/__lab4/auth', ['X-Lab4-Original-URI' => $this->uri($this->link(), true)])
            ->assertForbidden();
    }

    public function test_soft_deleted_link_is_rejected_even_with_valid_signature(): void
    {
        $link = $this->link();
        $uri = $this->uri($link);
        $link->delete();
        $this->getJson('/__lab4/auth', ['X-Lab4-Original-URI' => $uri])->assertForbidden();
    }
}
