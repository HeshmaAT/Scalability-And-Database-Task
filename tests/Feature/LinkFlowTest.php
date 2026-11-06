<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LinkFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_link_with_a_generated_code(): void
    {
        $response = $this->createLink();

        $response->assertCreated()
            ->assertJsonPath('destination_url', 'https://example.com/article');

        $code = $response->json('short_code');

        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{10}\z/', $code);
        $this->assertDatabaseHas('links', ['short_code' => $code]);
    }

    public function test_it_rejects_a_duplicate_alias(): void
    {
        $this->createLink(['custom_alias' => 'my_article'])->assertCreated();

        $this->postJson('/api/links', [
            'destination_url' => 'https://example.com/other',
            'custom_alias' => 'my_article',
        ])->assertStatus(409);
    }

    public function test_it_redirects_an_active_link(): void
    {
        $link = $this->createLink()->assertCreated()->json();

        $this->get('/' . $link['short_code'])
            ->assertStatus(302)
            ->assertHeader('Location', 'https://example.com/article');
    }

    public function test_pausing_and_reactivating_a_link_changes_its_redirect(): void
    {
        $link = $this->createLink()->assertCreated()->json();
        $redirect = '/' . $link['short_code'];
        $update = '/api/links/' . $link['id'];

        $this->patchJson($update, ['is_active' => false])->assertOk();
        $this->get($redirect)->assertNotFound();

        $this->patchJson($update, ['is_active' => true])->assertOk();
        $this->get($redirect)->assertStatus(302);
    }

    public function test_an_expired_active_link_returns_gone(): void
    {
        $link = $this->createLink([
            'expires_at' => now('UTC')->addMinute()->toISOString(),
        ])->assertCreated()->json();

        $this->travel(2)->minutes();

        $this->get('/' . $link['short_code'])->assertStatus(410);
    }

    private function createLink(array $overrides = [])
    {
        Sanctum::actingAs(User::factory()->create());

        return $this->postJson('/api/links', array_replace([
            'destination_url' => 'https://example.com/article',
        ], $overrides));
    }
}
