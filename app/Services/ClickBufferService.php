<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ClickBufferService
{
    private const PENDING = 'analytics:pending';
    private const PROCESSING = 'analytics:processing';

    public function record(int $linkId, string $ip): void
    {
        $event = json_encode([
            'link_id' => $linkId,
            'clicked_at' => now('UTC')->format('Y-m-d H:i:s'),
            'visitor_hash' => hash_hmac(
                'sha256',
                'click-visitor:' . $ip,
                config('app.key'),
            ),
        ], JSON_THROW_ON_ERROR);

        Redis::connection('queue')->rpush(self::PENDING, $event);
    }

    public function claim(): ?string
    {
        $batchId = (string) Str::uuid();

        $script = <<<'LUA'
local events = redis.call('LRANGE', KEYS[1], 0, tonumber(ARGV[2]) - 1)

if #events == 0 then
    return 0
end

redis.call('HSET', KEYS[2], ARGV[1], cjson.encode(events))
redis.call('LTRIM', KEYS[1], #events, -1)

return 1
LUA;

        $claimed = Redis::connection('queue')->eval(
            $script,
            2,
            self::PENDING,
            self::PROCESSING,
            $batchId,
            config('analytics.batch_size'),
        );

        return $claimed ? $batchId : null;
    }

    public function processingIds(): array
    {
        return Redis::connection('queue')->hkeys(self::PROCESSING);
    }

    public function events(string $batchId): array
    {
        $payload = Redis::connection('queue')->hget(self::PROCESSING, $batchId);

        if ($payload === null || $payload === false) {
            return [];
        }

        $events = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            fn (string $event) => json_decode($event, true, 512, JSON_THROW_ON_ERROR),
            $events,
        );
    }

    public function acknowledge(string $batchId): void
    {
        Redis::connection('queue')->hdel(self::PROCESSING, $batchId);
    }
}
