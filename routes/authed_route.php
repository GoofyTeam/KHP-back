<?php

use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\LossController;
use App\Http\Controllers\PreparationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Route utilisateur
Route::get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
})->name('user');

// Groupe de routes pour les utilisateurs
Route::prefix('user')->name('user.')->group(function () {
    Route::put('/update/info', [UserController::class, 'updateInfo'])->name('update.info');
    Route::put('/update/password', [UserController::class, 'updatePassword'])->name('update.password');
});

// Groupe de routes pour les préparations
Route::prefix('preparations')->name('preparations.')->group(function () {
    Route::post('/', [PreparationController::class, 'store'])->name('store');
    Route::put('/{id}', [PreparationController::class, 'update'])->name('update');
    Route::delete('/{id}', [PreparationController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/prepare', [PreparationController::class, 'prepare'])->name('prepare');
    Route::post('/{id}/adjust-quantity', [PreparationController::class, 'adjustQuantity'])->name('adjust-quantity');
});

// Groupe de routes pour les ingrédients
Route::prefix('ingredients')->name('ingredients.')->group(function () {
    Route::post('/', [IngredientController::class, 'store'])->name('store');
    Route::put('/{ingredient}', [IngredientController::class, 'update'])->name('update');
    Route::delete('/{ingredient}', [IngredientController::class, 'destroy'])->name('destroy');
    Route::post('/{ingredient}/adjust-quantity', [IngredientController::class, 'adjustQuantity'])->name('adjust-quantity');
});

// Groupe de routes pour les types de localisation
Route::prefix('location-types')->name('location-types.')->group(function () {
    Route::post('/', [LocationTypeController::class, 'store'])->name('store');
    Route::put('/{id}', [LocationTypeController::class, 'update'])->name('update');
    Route::delete('/{id}', [LocationTypeController::class, 'destroy'])->name('destroy');
});

// Groupe de routes pour les emplacements
Route::prefix('location')->name('location.')->group(function () {
    Route::post('/', [LocationController::class, 'store'])->name('store');
    Route::put('/{id}', [LocationController::class, 'update'])->name('update');
    Route::delete('/{id}', [LocationController::class, 'destroy'])->name('destroy');
    Route::post('/assign-type', [LocationController::class, 'assignType'])->name('assign-type');
});

// Groupe de routes pour les pertes
Route::prefix('losses')->name('losses.')->group(function () {
    Route::post('/', [LossController::class, 'store'])->name('store');
    Route::delete('/rollback/{loss}', [LossController::class, 'rollback'])->name('rollback');
});

// Routes utilitaires
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
})->where('path', '.*')->name('image-proxy');
