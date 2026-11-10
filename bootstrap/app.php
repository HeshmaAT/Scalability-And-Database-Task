<?php

use App\Http\Controllers\Api\QrImageController;
use App\Http\Controllers\RedirectLinkController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/{shortCode}', RedirectLinkController::class)
                ->where('shortCode', '[A-Za-z0-9_-]{3,32}');
            Route::get('/qr/{linkId}', QrImageController::class)
                ->whereNumber('linkId')
                ->middleware('signed')
                ->name('qr.image');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\PDOException $exception) {
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

            $connectionFailed = in_array($driverCode, [2002, 2003, 2006, 2013], true)
                || str_starts_with($sqlState, '08');

            if (! $connectionFailed) {
                return null;
            }

            return response()->json(
                ['message' => 'Database temporarily unavailable. Try again shortly.'],
                503,
                [
                    'Retry-After' => '5',
                    'Cache-Control' => 'no-store',
                ],
            );
        });
    })->create();
