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
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
        if (config('lab6.enabled')) { require base_path('routes/channels.php'); }

        $egressProxy = config('egress.proxy');

        if (is_string($egressProxy) && $egressProxy !== '') {
            \Illuminate\Support\Facades\Http::globalOptions([
                'proxy' => ['http' => $egressProxy, 'https' => $egressProxy, 'no' => []],
                'connect_timeout' => 3,
                'timeout' => 5,
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                    'strict' => true,
                    'referer' => false,
                ],
            ]);
        }

        RateLimiter::for('create-links', function (Request $request) {
            return Limit::perMinute(config('links.create_per_minute'))
                ->by('create-links:user:' . $request->user()->getAuthIdentifier());
        });
    }
}
