<?php

namespace App\Services;

use App\Models\Link;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Illuminate\Support\Facades\Log;
use PDOException;
class LinkCacheService
{
    private const TTL_SECONDS = 600;
    private const NEGATIVE_TTL_SECONDS = 30;

    public function get(string $code): array
    {
        $cached = Cache::get($this->key($code));

        if ($cached !== null) {
            return $cached;
        }

        return $this->synchronized($code, function () use ($code): array {
            $cached = Cache::get($this->key($code));

            if ($cached !== null) {
                return $cached;
            }

            return $this->load($code);
        });
    }

    public function refresh(string $code): void
    {
        $this->synchronized($code, function () use ($code): void {
            $this->load($code, true);
        });
    }

    public function synchronized(string $code, Closure $callback): mixed
    {
        try {
            return Cache::store('link_locks')
                ->lock('link-lock:' . $code, 30)
                ->block(3, $callback);
        } catch (LockTimeoutException $exception) {
            throw new ServiceUnavailableHttpException(
                1,
                'Link is busy. Try again.',
                $exception,
            );
        }
    }

    public function put(Link $link): array
    {
        $expiry = $link->expires_at === null
            ? null
            : Carbon::parse($link->expires_at, 'UTC');

        $payload = [
            'id' => $link->id,
            'destination_url' => $link->destination_url,
            'is_active' => (bool) $link->is_active,
            'expires_at' => $expiry?->getTimestamp(),
        ];

        $cacheUntil = now('UTC')->addSeconds(self::TTL_SECONDS);

        if ($expiry !== null && $expiry->lt($cacheUntil)) {
            $cacheUntil = $expiry;
        }

        if ($cacheUntil->isFuture()) {
            Cache::put($this->key($link->short_code), $payload, $cacheUntil);
        } else {
            Cache::forget($this->key($link->short_code));
        }

        return $payload;
    }

    private function load(string $code, bool $primaryOnly = false): array
    {
        $query = Link::query()
            ->select(['id', 'short_code', 'destination_url', 'is_active', 'expires_at'])
            ->where('short_code', $code);

        $link = null;

        if (! $primaryOnly) {
            try {
                $link = (clone $query)->first();
            } catch (PDOException $exception) {
                $driverCode = (int) ($exception->errorInfo[1] ?? 0);
                $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

                $connectionFailed = in_array($driverCode, [2002, 2003, 2006, 2013], true)
                    || str_starts_with($sqlState, '08');

                if (! $connectionFailed) {
                    throw $exception;
                }

                Log::warning('Replica read failed; trying the primary.', [
                    'driver_code' => $driverCode,
                ]);
            }
        }

        if ($link === null) {
            $link = (clone $query)->useWritePdo()->first();
        }

        if ($link !== null) {
            return $this->put($link);
        }

        $payload = ['missing' => true];

        Cache::put($this->key($code), $payload, self::NEGATIVE_TTL_SECONDS);

        return $payload;
    }
    private function key(string $code): string
    {
        return 'link:' . $code;
    }
}
