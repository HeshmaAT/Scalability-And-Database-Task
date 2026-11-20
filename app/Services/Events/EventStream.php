<?php
namespace App\Services\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class EventStream
{
    public function client(): \Redis
    {
        $redis = new \Redis();
        $redis->connect(config('events.host'), (int) config('events.port'), 0.15);
        $redis->setOption(\Redis::OPT_READ_TIMEOUT, 0.20);
        return $redis;
    }

    public function append(string $type, int $linkId, array $payload, ?string $eventId = null): string
    {
        $event = [
            'event_id' => $eventId ?? (string) Str::uuid(),
            'event_type' => $type,
            'occurred_at' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
            'schema_version' => 1,
            'ordering_key' => (string) $linkId,
            'payload' => ['link_id' => $linkId] + $payload,
        ];
        $redis = $this->client();
        try {
            $id = $redis->rawCommand('XADD', config('events.stream'), 'MAXLEN', '~',
                (string) config('events.maxlen'), '*', 'event', json_encode($event, JSON_THROW_ON_ERROR));
            if (!is_string($id)) throw new RuntimeException('Stream append returned no ID');
            return $id;
        } finally { $redis->close(); }
    }

    // Best effort: an accepted-but-unconfirmed append is NOT automatically retried.
    public function recordClick(int $linkId, string $ip): ?string
    {
        try {
            return $this->append('LinkClicked', $linkId, [
                'clicked_at' => now('UTC')->format('Y-m-d H:i:s'),
                'visitor_hash' => hash_hmac('sha256', 'click-visitor:'.$ip, config('app.key')),
            ]);
        } catch (\Throwable $exception) {
            // Do not log the visitor address, payload, token, or exception credentials.
            try { Log::warning('click_stream_publish_failed', ['link_id' => $linkId, 'exception_type' => get_class($exception)]); }
            catch (\Throwable) { /* Logging failure must not turn a redirect into an error. */ }
            return null;
        }
    }

    public function initialize(): void
    {
        $redis = $this->client();
        try {
            foreach (['analytics-writer', 'dashboard', 'milestones'] as $group) {
                try {
                    $reply = $redis->rawCommand('XGROUP', 'CREATE', config('events.stream'), $group, '0', 'MKSTREAM');
                    if ($reply === false && !str_contains((string) $redis->getLastError(), 'BUSYGROUP')) {
                        throw new RuntimeException('Cannot create stream group');
                    }
                } catch (\RedisException $e) {
                    if (!str_contains($e->getMessage(), 'BUSYGROUP')) throw $e;
                }
            }
        } finally { $redis->close(); }
    }
}
