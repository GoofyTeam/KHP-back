<?php

use App\Http\Controllers\IngredientController;
use App\Http\Controllers\PreparationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
})->name('user');

Route::post('/preparations', [PreparationController::class, 'store'])
    ->name('preparations.store');

Route::put('/preparations/{id}', [PreparationController::class, 'update'])
    ->name('preparations.update');

Route::post('/ingredients', [IngredientController::class, 'store'])
    ->name('ingredients.store');

Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])
    ->name('ingredients.update');

Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy']);

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
