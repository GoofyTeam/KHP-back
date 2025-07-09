<?php

use App\Http\Controllers\IngredientController;
use App\Http\Controllers\PreparationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
