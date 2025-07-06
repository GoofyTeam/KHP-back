<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreparationController;

Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
})->name('user');

Route::post('/preparations', [PreparationController::class, 'store'])
    ->name('preparations.store');

Route::put('/preparations/{id}', [PreparationController::class, 'update'])
    ->name('preparations.update');