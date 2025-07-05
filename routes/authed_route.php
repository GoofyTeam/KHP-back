<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
})->name('user');

Route::get('/preparation', function (Request $request) {
    return response()->json([
        'preparation' => $request->preparation(),
    ]);
})->name('preparation');
