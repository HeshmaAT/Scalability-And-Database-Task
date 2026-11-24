<?php
namespace Tests\Feature;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class LinkDashboardTest extends TestCase
{
    use DatabaseTransactions;
    private function fixture(User $owner):Link{return Link::forceCreate(['user_id'=>$owner->id,
        'short_code'=>'lab6test_'.bin2hex(random_bytes(6)),'destination_url'=>'https://example.com/dashboard',
        'is_active'=>true,'click_count'=>42]);}
    public function test_guest_cannot_read_dashboard_counts():void{
        $this->getJson('/api/lab6/links/1/snapshot')->assertUnauthorized();
    }
    public function test_owner_sees_real_count_and_private_no_store_response():void{
        $owner=User::factory()->create();$link=$this->fixture($owner);Sanctum::actingAs($owner);
        $response=$this->getJson('/api/lab6/links/'.$link->id.'/snapshot')->assertOk()
            ->assertJsonPath('link_id',$link->id)->assertJsonPath('total_clicks',42);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
    }
    public function test_other_user_cannot_read_owner_link_or_long_poll_it():void{
        $owner=User::factory()->create();$link=$this->fixture($owner);Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/lab6/links/'.$link->id.'/snapshot')->assertNotFound();
        $this->getJson('/api/lab6/links/'.$link->id.'/poll?since=0&timeout=1')->assertNotFound();
    }
    public function test_long_poll_returns_immediately_when_count_changed():void{
        $owner=User::factory()->create();$link=$this->fixture($owner);Sanctum::actingAs($owner);
        $this->getJson('/api/lab6/links/'.$link->id.'/poll?since=41&timeout=1')->assertOk()
            ->assertJsonPath('changed',true)->assertJsonPath('total_clicks',42);
    }
    public function test_long_poll_returns_unchanged_after_timeout():void{
        $owner=User::factory()->create();$link=$this->fixture($owner);Sanctum::actingAs($owner);
        $this->getJson('/api/lab6/links/'.$link->id.'/poll?since=42&timeout=1')->assertOk()
            ->assertJsonPath('changed',false)->assertJsonPath('total_clicks',42);
    }
    public function test_link_list_contains_only_owner_links():void{
        $owner=User::factory()->create();$owned=$this->fixture($owner);$other=$this->fixture(User::factory()->create());
        Sanctum::actingAs($owner);
        $data=$this->getJson('/api/lab6/links')->assertOk()->json('links');
        $ids=array_column($data,'id');$this->assertContains($owned->id,$ids);$this->assertNotContains($other->id,$ids);
    }
}
