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
