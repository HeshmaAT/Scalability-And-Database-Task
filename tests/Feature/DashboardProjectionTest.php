<?php
namespace Tests\Feature;
use App\Models\Link;use App\Models\User;use App\Services\Dashboard\DashboardProjection;
use Illuminate\Foundation\Testing\DatabaseTransactions;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;use Tests\TestCase;
class DashboardProjectionTest extends TestCase{
    use DatabaseTransactions;
    public function test_projection_requires_committed_analytics_and_deduplicates_replay():void{
        $u=User::factory()->create();$l=Link::forceCreate(['user_id'=>$u->id,'short_code'=>'lab6p_'.bin2hex(random_bytes(5)),'destination_url'=>'https://example.com','is_active'=>true,'click_count'=>5]);
        $event=['event_id'=>(string)Str::uuid(),'event_type'=>'LinkClicked','occurred_at'=>now('UTC')->toISOString(),'schema_version'=>1,'ordering_key'=>(string)$l->id,'payload'=>['link_id'=>$l->id]];
        $service=app(DashboardProjection::class);$blocked=false;
        try{$service->store($event);}catch(\LogicException){$blocked=true;}$this->assertTrue($blocked);
        DB::table('stream_processed_events')->insert(['consumer_group'=>'analytics-writer','event_id'=>$event['event_id'],'claim_token'=>(string)Str::uuid(),'consumer_name'=>'test','link_id'=>$l->id,'payload_hash'=>str_repeat('a',64),'processed_at'=>now('UTC')]);
        $a=$service->store($event);$b=$service->store($event);$this->assertSame($a->id,$b->id);
        $this->assertSame(1,DB::table('dashboard_events')->where('event_id',$event['event_id'])->count());
        $this->assertSame(5,(int)$a->total_clicks);
    }
}
