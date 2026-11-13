<?php
namespace Tests\Feature;

use App\Jobs\ProcessClickBatch;
use App\Models\Link;
use App\Services\ClickMaintenanceService;
use App\Services\Part9\DatabaseClickBufferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Part9DatabaseBufferTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_buffer_keeps_batch_replay_safe(): void
    {
        $link = Link::factory()->create();
        $buffer = new DatabaseClickBufferService;
        $buffer->record($link->id, '127.0.0.1');
        $buffer->record($link->id, '127.0.0.2');
        $id = $buffer->claim();
        $this->assertNotNull($id);
        $this->assertCount(2, $buffer->events($id));
        $maintenance = app(ClickMaintenanceService::class);
        (new ProcessClickBatch($id))->handle($buffer, $maintenance);
        (new ProcessClickBatch($id))->handle($buffer, $maintenance);
        $this->assertDatabaseCount('link_clicks', 2);
        $this->assertDatabaseCount('processed_click_batches', 1);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 2]);
        $this->assertSame([], $buffer->processingIds());
        $this->assertNull($buffer->claim());
    }
}
