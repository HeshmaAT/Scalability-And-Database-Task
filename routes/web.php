<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instance', function () {
    return response()->json([
        'hostname' => gethostname(),
    ]);
});

if (config('lab6.enabled') && app()->environment('local', 'testing')) {
    Route::get('/lab6', fn () => view('lab6.dashboard'));
}
