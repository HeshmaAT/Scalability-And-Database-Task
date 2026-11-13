<?php
namespace App\Services\Part9;

use App\Models\Link;
use App\Services\LinkCacheService;
use Closure;
use Illuminate\Support\Carbon;

class UncachedLinkCacheService extends LinkCacheService
{
    public function get(string $code): array
    {
        $link = Link::query()->useWritePdo()
            ->where('short_code', $code)->first();

        return $link === null ? ['missing' => true] : $this->put($link);
    }

    public function refresh(string $code): void {}

    public function synchronized(string $code, Closure $callback): mixed
    {
        return $callback();
    }

    public function put(Link $link): array
    {
        return [
            'id' => $link->id,
            'destination_url' => $link->destination_url,
            'is_active' => (bool) $link->is_active,
            'expires_at' => $link->expires_at === null
                ? null : Carbon::parse($link->expires_at, 'UTC')->getTimestamp(),
        ];
    }
}
