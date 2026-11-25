<?php
namespace Tests\Feature;
use App\Services\Events\EventEnvelope;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class Lab7SafetyTest extends TestCase
{
    public function test_fault_injection_cannot_target_live_stream():void{
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('explicitly enabled isolated Lab 7 stream');
        Artisan::call('events:consume',['--once'=>true,'--fault'=>'after-read']);
    }
    public function test_live_claim_idle_timeout_cannot_be_shortened():void{
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('explicitly enabled isolated Lab 7 stream');
        Artisan::call('events:consume',['--once'=>true,'--claim-idle-ms'=>2000]);
    }
    public function test_unknown_fault_point_is_rejected():void{
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported fault point');
        Artisan::call('events:consume',['--once'=>true,'--fault'=>'arbitrary']);
    }
    public function test_invalid_click_contract_is_rejected():void{
        $this->expectException(\InvalidArgumentException::class);
        (new EventEnvelope)->decode(json_encode(['event_id'=>(string)Str::uuid(),'event_type'=>'LinkClicked','schema_version'=>1,'payload'=>['link_id'=>1,'visitor_hash'=>'broken']]));
    }
    public function test_valid_click_envelope_preserves_stable_uuid():void{
        $event=['event_id'=>(string)Str::uuid(),'event_type'=>'LinkClicked','schema_version'=>1,'payload'=>['link_id'=>1,'visitor_hash'=>str_repeat('a',64),'clicked_at'=>'2026-11-25 10:00:00']];
        $this->assertSame($event,(new EventEnvelope)->decode(json_encode($event)));
    }
}
