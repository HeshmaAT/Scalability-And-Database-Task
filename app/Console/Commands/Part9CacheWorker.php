<?php
namespace App\Console\Commands;

use App\Models\Link;
use App\Services\LinkCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Part9CacheWorker extends Command
{
    protected $signature = 'loadtest:cache-worker {code} {--start-at=} {--unsafe}';
    protected $description = 'Isolated cache-stampede measurement worker';

    public function handle(LinkCacheService $cache): int
    {
        if (! app()->environment('local', 'testing')) { throw new RuntimeException('Local only.'); }
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (preg_match('/\bfrom\s+`?links`?\s/i', $query->sql)) {
                $queries++;
                // Same injected 100ms post-query delay in both cases.
                // This widens the cold-fill window; it is NOT production latency.
                usleep(100000);
            }
        });
        $start = (float) $this->option('start-at');
        while (microtime(true) < $start) { usleep(10000); }
        $began = microtime(true);
        $code = $this->argument('code');
        if ($this->option('unsafe')) {
            $payload = cache()->get('link:'.$code);
            if ($payload === null) {
                $link = Link::query()->where('short_code', $code)->first();
                if ($link === null) { $link = Link::query()->useWritePdo()->where('short_code', $code)->firstOrFail(); }
                $payload = $cache->put($link);
            }
        } else {
            $payload = $cache->get($code);
        }
        if (($payload['missing'] ?? false) || ! ($payload['is_active'] ?? false)) {
            throw new RuntimeException('Fixture link is not active.');
        }
        $this->line(json_encode([
            'queries' => $queries,
            'elapsed_ms' => round((microtime(true)-$began)*1000, 2),
            'started_at' => $began,
            'success' => true,
        ], JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
