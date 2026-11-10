<?php

namespace Tests\Feature;

use App\Jobs\ProcessClickBatch;
use App\Models\Link;
use App\Services\ClickBufferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use App\Services\ClickMaintenanceService;

class ClickBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_a_batch_does_not_duplicate_clicks(): void
    {
        $link = Link::factory()->create();
        $batchId = (string) Str::uuid();

        $events = [
            [
                'link_id' => $link->id,
                'clicked_at' => now('UTC')->format('Y-m-d H:i:s'),
                'visitor_hash' => hash('sha256', 'visitor-one'),
            ],
            [
                'link_id' => $link->id,
                'clicked_at' => now('UTC')->format('Y-m-d H:i:s'),
                'visitor_hash' => hash('sha256', 'visitor-two'),
            ],
        ];

        $buffer = Mockery::mock(ClickBufferService::class);

        $buffer->shouldReceive('events')
            ->with($batchId)
            ->twice()
            ->andReturn($events);

        $buffer->shouldReceive('acknowledge')
            ->with($batchId)
            ->twice();

        $maintenance = app(ClickMaintenanceService::class);

        (new ProcessClickBatch($batchId))->handle($buffer, $maintenance);
        (new ProcessClickBatch($batchId))->handle($buffer, $maintenance);

        $this->assertDatabaseCount('link_clicks', 2);
        $this->assertDatabaseCount('processed_click_batches', 1);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 2,
        ]);

        $this->assertDatabaseHas('processed_click_batches', [
            'id' => $batchId,
        ]);
    }
}
