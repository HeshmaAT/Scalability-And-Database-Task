<?php
namespace Tests\Feature;
use App\Models\Link;use App\Models\User;use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\DatabaseTransactions;use Tests\TestCase;
class RealtimeChannelTest extends TestCase{
    use DatabaseTransactions;
    private function fixture(User $owner):Link{return Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab6ws_'.bin2hex(random_bytes(5)),'destination_url'=>'https://example.com/ws','is_active'=>true]);}
    public function test_guest_cannot_authorize_private_channel():void{$this->postJson('/api/lab6/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>'private-links.1'])->assertUnauthorized();}
    public function test_user_b_cannot_subscribe_to_user_a_link_channel():void{
        $a=User::factory()->create();$link=$this->fixture($a);Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/lab6/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>'private-links.'.$link->id])->assertForbidden();
    }
    public function test_owner_receives_valid_private_channel_signature():void{
        $a=User::factory()->create();$link=$this->fixture($a);Sanctum::actingAs($a);$channel='private-links.'.$link->id;
        $r=$this->postJson('/api/lab6/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>$channel])->assertOk();
        $expected=config('broadcasting.connections.reverb.key').':'.hash_hmac('sha256','123.456:'.$channel,config('broadcasting.connections.reverb.secret'));
        $r->assertJsonPath('auth',$expected);
    }
    public function test_catchup_history_is_private_and_cursor_ordered():void{
        $owner=User::factory()->create();$link=$this->fixture($owner);$ids=[];
        for($i=0;$i<2;$i++)$ids[]=\Illuminate\Support\Facades\DB::table('dashboard_events')->insertGetId([
            'event_id'=>(string)\Illuminate\Support\Str::uuid(),'link_id'=>$link->id,'source_hash'=>str_repeat('a',64),
            'total_clicks'=>$i+1,'occurred_at'=>now('UTC')->toISOString(),'projected_at'=>now('UTC')]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/lab6/links/'.$link->id.'/events?since='.$ids[0])->assertOk()->assertJsonCount(1,'events')->assertJsonPath('next_cursor',$ids[1]);
        Sanctum::actingAs(User::factory()->create());$this->getJson('/api/lab6/links/'.$link->id.'/events?since=0')->assertNotFound();
    }
}
