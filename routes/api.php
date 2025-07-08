<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/register', [AuthController::class, 'register'])->name('register');

    Route::post('/password/email', [AuthController::class, 'send_password_reset_email'])->name('password_email');

    Route::middleware('auth')->group(function () {
        include base_path('routes/authed_route.php');
    });

});
