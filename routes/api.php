<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');



Route::post('/links', [LinkController::class, 'store'])
    ->middleware('auth:sanctum');
Route::patch('/links/{id}', [LinkController::class, 'update'])
    ->whereNumber('id')
    ->middleware('auth:sanctum');




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/links/{id}/stats', [LinkController::class, 'stats'])->whereNumber('id');
    Route::post('/links/{id}/qr', [LinkController::class, 'requestQr'])->whereNumber('id');
    Route::get('/links/{id}/qr', [LinkController::class, 'showQr'])->whereNumber('id');
});
