<?php

namespace Tests\Feature;

use App\Jobs\ProcessClickBatch;
use App\Models\Link;
use App\Services\ClickBufferService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentClickWorkersTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_workers_preserve_all_click_increments(): void
    {
        $link = Link::factory()->create();
        $buffer = app(ClickBufferService::class);
        $queue = 'click-test-' . Str::uuid();

        config(['analytics.batch_size' => 50]);

        for ($number = 0; $number < 100; $number++) {
            $buffer->record($link->id, '192.0.2.1');
        }

        $firstBatch = $buffer->claim();
        $secondBatch = $buffer->claim();

        $this->assertNotNull($firstBatch);
        $this->assertNotNull($secondBatch);

        ProcessClickBatch::dispatch($firstBatch)
            ->onConnection('redis')
            ->onQueue($queue);

        ProcessClickBatch::dispatch($secondBatch)
            ->onConnection('redis')
            ->onQueue($queue);

        $workers = [
            $this->worker($queue),
            $this->worker($queue),
        ];

        try {
            foreach ($workers as $worker) {
                $worker->start();
            }

            foreach ($workers as $worker) {
                $worker->wait();

                $this->assertTrue(
                    $worker->isSuccessful(),
                    $worker->getOutput() . $worker->getErrorOutput(),
                );
            }

            $this->assertDatabaseCount('link_clicks', 100);
            $this->assertDatabaseCount('processed_click_batches', 2);

            $this->assertDatabaseHas('links', [
                'id' => $link->id,
                'click_count' => 100,
            ]);

            $this->assertSame(
                100,
                DB::table('link_clicks')->where('link_id', $link->id)->count(),
            );
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }

            $buffer->acknowledge($firstBatch);
            $buffer->acknowledge($secondBatch);
        }
    }

    private function worker(string $queue): Process
    {
        return new Process(
            [
                PHP_BINARY,
                base_path('artisan'),
                'queue:work',
                'redis',
                '--queue=' . $queue,
                '--once',
                '--tries=1',
                '--timeout=30',
            ],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => '/tmp/part6-worker-test-config.php',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => 'url_shortener_test',
                'CACHE_STORE' => 'array',
                'REDIS_PREFIX' => 'part6-concurrency:',
            ],
            timeout: 45,
        );
    }
}
