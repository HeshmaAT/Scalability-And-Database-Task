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
    }

    public function boot(): void
    {
        RateLimiter::for('create-links', function (Request $request) {
            return Limit::perMinute(config('links.create_per_minute'))
                ->by('create-links:user:' . $request->user()->getAuthIdentifier());
        });
    }
}
