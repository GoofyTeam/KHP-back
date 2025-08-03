<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/register', [AuthController::class, 'register'])->name('register');

    Route::post('password/token', [AuthController::class, 'sendEmailToken'])->name('email_token');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password_reset');

    Route::middleware('auth')->group(function () {
        include base_path('routes/authed_route.php');
    });

});
