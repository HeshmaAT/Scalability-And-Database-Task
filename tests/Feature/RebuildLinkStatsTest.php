<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebuildLinkStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_an_incorrect_click_counter(): void
    {
        $link = Link::factory()->create([
            'click_count' => 999,
        ]);

        $rows = [];

        for ($number = 1; $number <= 3; $number++) {
            $rows[] = [
                'link_id' => $link->id,
                'clicked_at' => now('UTC'),
                'visitor_hash' => hash('sha256', 'visitor-' . $number, true),
            ];
        }

        DB::table('link_clicks')->insert($rows);

        $this->artisan('stats:rebuild', ['--link' => $link->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'click_count' => 3,
        ]);

        $this->assertDatabaseCount('link_clicks', 3);
    }

    public function test_rebuild_preserves_archived_clicks(): void
    {
        $link = Link::factory()->create([
            'click_count' => 999,
            'archived_click_count' => 7,
        ]);

        DB::table('link_clicks')->insert(array_fill(0, 3, [
            'link_id' => $link->id,
            'clicked_at' => now('UTC'),
            'visitor_hash' => hash('sha256', 'test-visitor', true),
        ]));

        $this->artisan('stats:rebuild', ['--link' => $link->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'archived_click_count' => 7,
            'click_count' => 10,
        ]);

        $this->assertDatabaseCount('link_clicks', 3);
    }
}
