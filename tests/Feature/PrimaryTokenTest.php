<?php
namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrimaryTokenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_token_queries_use_primary_connection(): void
    {
        $this->assertSame(PersonalAccessToken::class, Sanctum::$personalAccessTokenModel);
        $this->assertTrue((new PersonalAccessToken)->newQuery()->getQuery()->useWritePdo);
    }

    public function test_new_token_is_valid_wrong_secret_and_revoked_token_are_rejected(): void
    {
        $issued = User::factory()->create()->createToken('primary-token-test');
        $plain = $issued->plainTextToken;
        $this->assertSame($issued->accessToken->id, PersonalAccessToken::findToken($plain)?->id);
        $this->assertNull(PersonalAccessToken::findToken($issued->accessToken->id.'|wrong-secret'));
        $issued->accessToken->delete();
        $this->assertNull(PersonalAccessToken::findToken($plain));
    }
}
