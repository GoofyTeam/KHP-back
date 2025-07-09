<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/register', [AuthController::class, 'register'])->name('register');

    Route::middleware('auth')->group(function () {
        include base_path('routes/authed_route.php');
    });

    Route::get('/image-proxy/{bucket}/{path}', function ($bucket, $path) {
        try {
            $fullPath = "{$bucket}/{$path}";
            $tempUrl = Storage::disk('s3')->temporaryUrl($fullPath, now()->addMinutes(5));
            $image = file_get_contents($tempUrl);

            return response($image)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Image not found'], 404);
        }
    })->where('path', '.*');
});
