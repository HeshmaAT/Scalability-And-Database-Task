<?php
namespace App\Services\Events;

class StreamFailures
{
    public const MAX_ATTEMPTS = 3;

    public static function retryKey(string $group, string $source): string
    {
        return 'events:retry:'.$group.':'.hash('sha256', $source);
    }

    public function malformed(\Redis $redis, string $group, string $source, string $id, mixed $fields, \Throwable $error): array
    {
        // Retry bookkeeping, final dead-letter append, and ACK are atomic on this broker.
        $lua = <<<'LUA'
local raw = redis.call('HGET', KEYS[3], ARGV[2])
local state = raw and cjson.decode(raw) or {attempts=0,next_at=0}
local now = tonumber(ARGV[3])
if now < tonumber(state.next_at) then return {0, tonumber(state.attempts)} end
local attempts = tonumber(state.attempts) + 1
if attempts >= tonumber(ARGV[4]) then
    local dead = redis.call('XADD', KEYS[2], 'MAXLEN', '~', '10000', '*',
        'source_stream', KEYS[1], 'source_id', ARGV[2], 'consumer_group', ARGV[1],
        'attempt_count', tostring(attempts), 'reason', ARGV[5], 'entry', ARGV[6], 'failed_at', ARGV[7])
    redis.call('XACK', KEYS[1], ARGV[1], ARGV[2])
    redis.call('HDEL', KEYS[3], ARGV[2])
    return {2, attempts, dead}
end
local delay = math.min(30, 0.2 * (2 ^ (attempts - 1)))
redis.call('HSET', KEYS[3], ARGV[2], cjson.encode({attempts=attempts,next_at=now+delay}))
return {1, attempts}
LUA;
        $reason = substr(get_class($error).': '.$error->getMessage(), 0, 300);
        $reply = $redis->rawCommand('EVAL', $lua, '3', $source, 'events:dead-letter:'.$group,
            self::retryKey($group, $source), $group, $id, (string)microtime(true),
            (string)self::MAX_ATTEMPTS, $reason, json_encode($fields, JSON_THROW_ON_ERROR), gmdate('c'));
        if (!is_array($reply)) throw new \RuntimeException('Retry/dead-letter transaction failed');
        return ['status'=>(int)$reply[0], 'attempts'=>(int)$reply[1], 'dead_id'=>$reply[2] ?? null];
    }

    public static function fields(array $flat): array
    {
        if (count($flat) % 2 !== 0) throw new \InvalidArgumentException('Malformed stream field list');
        $result=[];for($i=0;$i<count($flat);$i+=2)$result[$flat[$i]]=$flat[$i+1];return $result;
    }
}
