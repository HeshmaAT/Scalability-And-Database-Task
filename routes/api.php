<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Lab\Lab3ClientInfoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');



Route::post('/links', [LinkController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:create-links']);
Route::patch('/links/{id}', [LinkController::class, 'update'])
    ->whereNumber('id')
    ->middleware('auth:sanctum');




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/links/{id}/stats', [LinkController::class, 'stats'])->whereNumber('id');
    Route::post('/links/{id}/qr', [LinkController::class, 'requestQr'])->whereNumber('id');
    Route::get('/links/{id}/qr', [LinkController::class, 'showQr'])->whereNumber('id');
});


if (app()->environment('local', 'testing') && config('lab3.enabled')) {
    Route::get('/lab3/client', Lab3ClientInfoController::class);
    Route::get('/lab3/limited', Lab3ClientInfoController::class)
        ->middleware('throttle:3,1');
}


if (app()->environment('local', 'testing') && config('lab4.enabled')) {
    Route::get('/lab4/qr-authorize', \App\Http\Controllers\Lab\Lab4QrAuthorizeController::class);
}
