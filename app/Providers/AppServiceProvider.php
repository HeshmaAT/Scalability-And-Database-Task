<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('part9.profile') === 'A' && app()->environment('local', 'testing')) {
            $this->app->bind(
                \App\Services\LinkCacheService::class,
                \App\Services\Part9\UncachedLinkCacheService::class,
            );
            $this->app->bind(
                \App\Services\ClickBufferService::class,
                \App\Services\Part9\DatabaseClickBufferService::class,
            );
            config(['cache.limiter' => 'database']);
            config(['cache.stores.link_locks' => config('cache.stores.database')]);
        }
    }

    public function boot(): void
    {
        RateLimiter::for('create-links', function (Request $request) {
            return Limit::perMinute(config('links.create_per_minute'))
                ->by('create-links:user:' . $request->user()->getAuthIdentifier());
        });
    }
}
