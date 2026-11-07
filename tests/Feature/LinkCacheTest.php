<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LinkCacheTest extends TestCase
{
    use RefreshDatabase;

    private array $cacheKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->cacheKeys as $key) {
                Cache::forget($key);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_a_link_without_expiry_is_cached_for_ten_minutes(): void
    {
        $link = $this->createLink();

        $this->get('/' . $link['short_code'])->assertStatus(302);

        $ttl = $this->ttl($link['short_code']);

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(600, $ttl);

        $this->assertSame(
            'https://example.com/article',
            Cache::get('link:' . $link['short_code'])['destination_url'],
        );
    }

    public function test_cache_ttl_does_not_exceed_link_expiry(): void
    {
        $link = $this->createLink([
            'expires_at' => now('UTC')->addSeconds(30)->toISOString(),
        ]);

        $ttl = $this->ttl($link['short_code']);

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(30, $ttl);
    }

    public function test_updates_refresh_cached_status_and_expiry(): void
    {
        $link = $this->createLink();
        $path = '/api/links/' . $link['id'];
        $redirect = '/' . $link['short_code'];
        $key = 'link:' . $link['short_code'];

        $this->get($redirect)->assertStatus(302);

        $this->patchJson($path, ['is_active' => false])->assertOk();

        $this->assertFalse(Cache::get($key)['is_active']);
        $this->get($redirect)->assertNotFound();

        $expiry = now('UTC')->addSeconds(30);

        $this->patchJson($path, [
            'is_active' => true,
            'expires_at' => $expiry->toISOString(),
        ])->assertOk();

        $this->assertTrue(Cache::get($key)['is_active']);
        $this->assertSame($expiry->getTimestamp(), Cache::get($key)['expires_at']);
        $this->assertGreaterThan(0, $this->ttl($link['short_code']));
        $this->assertLessThanOrEqual(30, $this->ttl($link['short_code']));

        $this->get($redirect)->assertStatus(302);

        $this->travel(31)->seconds();

        $this->get($redirect)->assertStatus(410);
    }

    public function test_creating_an_alias_replaces_its_negative_cache(): void
    {
        $alias = 'cache_' . bin2hex(random_bytes(6));
        $key = 'link:' . $alias;
        $this->cacheKeys[] = $key;

        $this->get('/' . $alias)->assertNotFound();

        $this->assertTrue(Cache::get($key)['missing']);
        $this->assertGreaterThan(0, $this->ttl($alias));
        $this->assertLessThanOrEqual(30, $this->ttl($alias));

        $this->postJson('/api/links', [
            'destination_url' => 'https://example.com/article',
            'custom_alias' => $alias,
        ])->assertCreated();

        $this->assertArrayNotHasKey('missing', Cache::get($key));

        $this->get('/' . $alias)
            ->assertStatus(302)
            ->assertHeader('Location', 'https://example.com/article');
    }

    private function createLink(array $overrides = []): array
    {
        $link = $this->postJson('/api/links', array_replace([
            'destination_url' => 'https://example.com/article',
        ], $overrides))->assertCreated()->json();

        $this->cacheKeys[] = 'link:' . $link['short_code'];

        return $link;
    }

    private function ttl(string $code): int
    {
        $store = Cache::store()->getStore();

        return (int) $store->connection()->ttl(
            $store->getPrefix() . 'link:' . $code,
        );
    }
}
