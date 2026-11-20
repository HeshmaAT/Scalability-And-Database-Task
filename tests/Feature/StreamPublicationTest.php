<?php
namespace Tests\Feature;
use App\Services\Events\EventStream;
use App\Services\ClickBufferService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
class StreamPublicationTest extends TestCase
{
    public function test_stream_failure_does_not_break_the_redirect_path(): void
    {
        config(['events.enabled' => true]);
        Log::shouldReceive('warning')->once()->with('click_stream_publish_failed', \Mockery::type('array'));
        $stream = new class extends EventStream {
            public function client(): \Redis { throw new \RedisException('Simulated broker failure'); }
        };
        $this->app->instance(EventStream::class, $stream);
        // Synthetic destination avoids touching real links or their cache.
        \Illuminate\Support\Facades\Route::get('/__lab5/click', function (ClickBufferService $buffer) {
            $buffer->record(42, '203.0.113.10');
            return redirect()->away('https://example.com/article', 302);
        });
        $this->get('/__lab5/click')->assertStatus(302)->assertHeader('Location', 'https://example.com/article');
    }
}
