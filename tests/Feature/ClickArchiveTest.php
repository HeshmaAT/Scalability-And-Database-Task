<?php

namespace Tests\Feature;

use App\Jobs\ProcessClickBatch;
use App\Models\Link;
use App\Services\ClickBufferService;
use App\Services\ClickMaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ClickArchiveTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_archiving_preserves_totals_and_handles_late_clicks(): void
    {
        CarbonImmutable::setTestNow('2027-02-10 12:00:00');

        $link = Link::factory()->create([
            'click_count' => 2,
            'archived_click_count' => 0,
        ]);

        $this->insertClick($link->id, '2026-07-15 12:00:00');
        $this->insertClick($link->id, '2026-11-15 12:00:00');

        $this->artisan('analytics:partitions:archive')
            ->assertExitCode(0);

        $this->assertDatabaseCount('link_clicks', 2);
        $this->assertDatabaseCount('click_archive_runs', 0);

        $this->artisan('analytics:partitions:archive', [
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('link_clicks', 1);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 2,
            'archived_click_count' => 1,
        ]);

        $this->assertDatabaseHas('click_archive_runs', [
            'partition_name' => 'p_history',
            'status' => 'completed',
        ]);

        // Running maintenance again must not count archived clicks twice.
        $this->artisan('analytics:partitions:archive', [
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 2,
            'archived_click_count' => 1,
        ]);

        // A delayed event from an archived month updates totals only.
        $batchId = (string) Str::uuid();

        $buffer = Mockery::mock(ClickBufferService::class);

        $buffer->shouldReceive('events')
            ->once()
            ->with($batchId)
            ->andReturn([
                [
                    'link_id' => $link->id,
                    'clicked_at' => '2026-07-20 12:00:00',
                    'visitor_hash' => hash('sha256', 'late-visitor'),
                ],
            ]);

        $buffer->shouldReceive('acknowledge')
            ->once()
            ->with($batchId);

        (new ProcessClickBatch($batchId))->handle(
            $buffer,
            app(ClickMaintenanceService::class),
        );

        $this->assertDatabaseCount('link_clicks', 1);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 3,
            'archived_click_count' => 2,
        ]);

        $this->artisan('stats:rebuild', [
            '--link' => (string) $link->id,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 3,
            'archived_click_count' => 2,
        ]);
    }

    public function test_it_recovers_after_partition_drop_without_double_counting(): void
    {
        CarbonImmutable::setTestNow('2027-02-10 12:00:00');

        $link = Link::factory()->create([
            'click_count' => 2,
            'archived_click_count' => 0,
        ]);

        $this->insertClick($link->id, '2026-07-15 12:00:00');
        $this->insertClick($link->id, '2026-11-15 12:00:00');

        $runId = DB::table('click_archive_runs')->insertGetId([
            'partition_name' => 'p_history',
            'upper_bound' => '2026-08-01 00:00:00',
            'status' => 'snapshot_ready',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('click_archive_counts')->insert([
            'archive_run_id' => $runId,
            'link_id' => $link->id,
            'clicks' => 1,
        ]);

        // Simulate interruption after DDL, before applying archived totals.
        DB::statement(
            'ALTER TABLE link_clicks DROP PARTITION p_history'
        );

        $this->artisan('stats:rebuild', [
            '--link' => (string) $link->id,
        ])->assertExitCode(1);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 2,
            'archived_click_count' => 0,
        ]);

        $this->artisan('analytics:partitions:archive', [
            '--execute' => true,
        ])->assertExitCode(0);

        $this->artisan('analytics:partitions:archive', [
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('click_archive_runs', [
            'id' => $runId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 2,
            'archived_click_count' => 1,
        ]);

        $this->assertDatabaseCount('link_clicks', 1);
    }

    private function insertClick(int $linkId, string $clickedAt): void
    {
        DB::table('link_clicks')->insert([
            'link_id' => $linkId,
            'clicked_at' => $clickedAt,
            'visitor_hash' => hash('sha256', 'test-visitor', true),
        ]);
    }
}
