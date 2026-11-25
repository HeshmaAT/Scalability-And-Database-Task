<?php
namespace App\Services\Events;

use Illuminate\Support\Str;

class EventEnvelope
{
    public function decode(string $json): array
    {
        $event = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($event) || ($event['schema_version'] ?? null) !== 1
            || !Str::isUuid($event['event_id'] ?? '')
            || !is_string($event['event_type'] ?? null) || $event['event_type'] === '') {
            throw new \InvalidArgumentException('Invalid event contract');
        }
        if ($event['event_type'] === 'LinkClicked') {
            $payload = $event['payload'] ?? [];
            if (!is_array($payload) || !is_int($payload['link_id'] ?? null) || $payload['link_id'] < 1
                || !is_string($payload['visitor_hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $payload['visitor_hash'])) {
                throw new \InvalidArgumentException('Invalid click payload');
            }
            $value = $payload['clicked_at'] ?? '';
            if (!is_string($value)) throw new \InvalidArgumentException('Invalid clicked_at');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d H:i:s') !== $value) throw new \InvalidArgumentException('Invalid clicked_at');
        }
        return $event;
    }
}
