<?php
namespace Tests\Feature;
use App\Models\Link;
use App\Models\LinkEvent;
use App\Models\User;
use App\Services\CreateLinkService;
use App\Services\UpdateLinkService;
use App\Services\Events\LinkLifecycle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LifecycleOutboxTest extends TestCase
{
    use DatabaseTransactions;
    protected function setUp():void{
        parent::setUp();config(['cache.default'=>'array','cache.stores.link_locks'=>['driver'=>'array'],'events.stream'=>'lab5:test:lab7:unit:'.bin2hex(random_bytes(5))]);app('cache')->forgetDriver('link_locks');
    }
    private function create():array{
        $owner=User::factory()->create();$link=app(CreateLinkService::class)->create($owner,'https://example.com/lifecycle','lab7unit_'.bin2hex(random_bytes(5)));return[$owner,$link];
    }
    public function test_create_writes_state_log_and_outbox_with_one_stable_uuid():void{
        [$owner,$link]=$this->create();$event=LinkEvent::query()->where('link_id',$link->id)->firstOrFail();
        $this->assertSame(1,(int)$link->lifecycle_version);$this->assertSame('LinkCreated',$event->type);
        $this->assertDatabaseHas('event_outbox',['event_id'=>$event->event_id,'event_type'=>'LinkCreated','published_at'=>null]);
        $this->assertSame(app(LinkLifecycle::class)->state($link),$event->payload['state']);
    }
    public function test_outer_rollback_leaves_no_row_event_outbox_or_cached_phantom():void{
        $owner=User::factory()->create();$code='lab7rollback_'.bin2hex(random_bytes(5));$id=$uuid=null;
        try{DB::transaction(function()use($owner,$code,&$id,&$uuid){$link=app(CreateLinkService::class)->create($owner,'https://example.com/rollback',$code);$id=$link->id;$uuid=DB::table('link_events')->where('link_id',$id)->value('event_id');throw new \RuntimeException('fixture rollback');});}
        catch(\RuntimeException $e){$this->assertSame('fixture rollback',$e->getMessage());}
        $this->assertDatabaseMissing('links',['short_code'=>$code]);$this->assertDatabaseMissing('link_events',['event_id'=>$uuid]);$this->assertDatabaseMissing('event_outbox',['event_id'=>$uuid]);$this->assertNull(Cache::get('link:'.$code));
    }
    public function test_http_update_exposes_version_and_rejects_stale_expected_version():void{
        [$owner,$link]=$this->create();\Laravel\Sanctum\Sanctum::actingAs($owner);
        $this->patchJson('/api/links/'.$link->id,['is_active'=>false,'expected_version'=>1])->assertOk()->assertJsonPath('version',2);
        $this->patchJson('/api/links/'.$link->id,['is_active'=>true,'expected_version'=>1])->assertStatus(409);
    }
    public function test_pause_resume_events_rebuild_current_state():void{
        [$owner,$link]=$this->create();$link=app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>false,'expected_version'=>1]);
        $link=app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>true,'expires_at'=>'2030-01-01T00:00:00Z','expected_version'=>2]);
        $this->assertSame(['LinkCreated','LinkPaused','LinkResumed'],LinkEvent::query()->where('link_id',$link->id)->orderBy('version')->pluck('type')->all());
        $rebuilt=app(LinkLifecycle::class)->replay([(int)$link->id]);$this->assertSame(3,$rebuilt[$link->id]['version']);$this->assertSame(app(LinkLifecycle::class)->state($link),$rebuilt[$link->id]['state']);
    }
    public function test_stale_version_is_rejected_without_an_extra_event():void{
        [$owner,$link]=$this->create();app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>false,'expected_version'=>1]);
        try{app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>true,'expected_version'=>1]);$this->fail('Stale version accepted');}catch(ConflictHttpException $e){$this->assertSame(409,$e->getStatusCode());}
        $this->assertSame(2,LinkEvent::query()->where('link_id',$link->id)->count());$this->assertFalse((bool)$link->fresh()->is_active);
    }
    public function test_existing_link_gets_import_baseline_not_invented_creation_history():void{
        $owner=User::factory()->create();$link=Link::forceCreate(['user_id'=>$owner->id,'short_code'=>'lab7legacy_'.bin2hex(random_bytes(4)),'destination_url'=>'https://example.com/legacy','is_active'=>true]);
        $link=app(UpdateLinkService::class)->update($owner,(string)$link->id,['is_active'=>false,'expected_version'=>0]);
        $this->assertSame(['LinkImported','LinkPaused'],LinkEvent::query()->where('link_id',$link->id)->orderBy('version')->pluck('type')->all());
        $this->assertSame(app(LinkLifecycle::class)->state($link),app(LinkLifecycle::class)->replay([(int)$link->id])[$link->id]['state']);
    }
    public function test_live_relay_fault_requires_explicit_isolated_opt_in():void{
        $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('explicitly enabled isolated Lab 7 stream');
        \Illuminate\Support\Facades\Artisan::call('events:relay',['--once'=>true,'--fault'=>'after-publish']);
    }
    public function test_event_model_rejects_updates():void{
        [$owner,$link]=$this->create();$event=LinkEvent::query()->where('link_id',$link->id)->firstOrFail();
        $this->expectException(\LogicException::class);$this->expectExceptionMessage('append-only');$event->update(['type'=>'LinkPaused']);
    }
    public function test_event_model_rejects_deletes():void{
        [$owner,$link]=$this->create();$event=LinkEvent::query()->where('link_id',$link->id)->firstOrFail();
        $this->expectException(\LogicException::class);$this->expectExceptionMessage('append-only');$event->delete();
    }
}
