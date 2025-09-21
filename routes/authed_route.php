<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationTypeController;
use App\Http\Controllers\LossController;
use App\Http\Controllers\LossReasonController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuTypeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PerishableController;
use App\Http\Controllers\PreparationController;
use App\Http\Controllers\QuickAccessController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TableController;
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

// Groupe de routes pour l'entreprise
Route::prefix('company')->name('company.')->group(function () {
    Route::put('/options', [CompanyController::class, 'updateOptions'])->name('options.update');
});

Route::put('/public-menus', [CompanyController::class, 'updateOptions'])->name('public-menus.update');

// Groupe de routes pour les préparations
Route::prefix('preparations')->name('preparations.')->group(function () {
    Route::post('/', [PreparationController::class, 'store'])->name('store');
    Route::put('/{id}', [PreparationController::class, 'update'])->name('update');
    Route::put('/{preparation}/threshold', [PreparationController::class, 'updateThreshold'])->name('update-threshold');
    Route::delete('/{preparation}/threshold', [PreparationController::class, 'resetThreshold'])->name('reset-threshold');
    Route::delete('/{id}', [PreparationController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/prepare', [PreparationController::class, 'prepare'])->name('prepare');
    Route::post('/{id}/add-quantity', [PreparationController::class, 'addQuantity'])->name('add-quantity');
    Route::post('/{id}/remove-quantity', [PreparationController::class, 'removeQuantity'])->name('remove-quantity');
    Route::post('/{id}/move-quantity', [PreparationController::class, 'moveQuantity'])->name('move-quantity');
});

// Groupe de routes pour les ingrédients
Route::prefix('ingredients')->name('ingredients.')->group(function () {
    Route::post('/bulk', [IngredientController::class, 'bulkStore'])->name('bulk-store');
    Route::post('/', [IngredientController::class, 'store'])->name('store');
    Route::put('/{ingredient}', [IngredientController::class, 'update'])->name('update');
    Route::put('/{ingredient}/threshold', [IngredientController::class, 'updateThreshold'])->name('update-threshold');
    Route::delete('/{ingredient}/threshold', [IngredientController::class, 'resetThreshold'])->name('reset-threshold');
    Route::delete('/{ingredient}', [IngredientController::class, 'destroy'])->name('destroy');
    Route::post('/{ingredient}/add-quantity', [IngredientController::class, 'addQuantity'])->name('add-quantity');
    Route::post('/{ingredient}/remove-quantity', [IngredientController::class, 'removeQuantity'])->name('remove-quantity');
    Route::post('/{ingredient}/move-quantity', [IngredientController::class, 'moveQuantity'])->name('move-quantity');
});

Route::prefix('perishables')->name('perishables.')->group(function () {
    Route::patch('/{perishableId}/read', [PerishableController::class, 'markAsRead'])->name('mark-as-read');
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

// Groupe de routes pour les raisons de perte
Route::prefix('loss-reasons')->name('loss-reasons.')->group(function () {
    Route::post('/', [LossReasonController::class, 'store'])->name('store');
    Route::put('/{id}', [LossReasonController::class, 'update'])->name('update');
    Route::delete('/{id}', [LossReasonController::class, 'destroy'])->name('destroy');
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

// Groupe de routes pour les menus
Route::prefix('menus')->name('menus.')->group(function () {
    Route::post('/', [MenuController::class, 'store'])->name('store');
    Route::put('/{id}', [MenuController::class, 'update'])->name('update');
    Route::delete('/{id}', [MenuController::class, 'destroy'])->name('destroy');
});

// Groupe de routes pour les catégories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::post('/', [CategoryController::class, 'store'])->name('store');
    Route::put('/{id}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy');
});

// Groupe de routes pour les catégories de menus
Route::prefix('menu-categories')->name('menu-categories.')->group(function () {
    Route::post('/', [MenuCategoryController::class, 'store'])->name('store');
    Route::put('/{id}', [MenuCategoryController::class, 'update'])->name('update');
    Route::delete('/{id}', [MenuCategoryController::class, 'destroy'])->name('destroy');
});

Route::prefix('menu-types')->name('menu-types.')->group(function () {
    Route::post('/', [MenuTypeController::class, 'store'])->name('store');
    Route::put('/{id}', [MenuTypeController::class, 'update'])->name('update');
    Route::delete('/{id}', [MenuTypeController::class, 'destroy'])->name('destroy');
});

// Groupe de routes pour les salles et tables
Route::prefix('rooms')->name('rooms.')->group(function () {
    Route::post('/', [RoomController::class, 'store'])->name('store');
    Route::put('/{id}', [RoomController::class, 'update'])->name('update');
    Route::delete('/{id}', [RoomController::class, 'destroy'])->name('destroy');

    Route::post('/{room}/tables', [TableController::class, 'store'])->name('tables.store');
    Route::put('/{room}/tables/{table}', [TableController::class, 'update'])->name('tables.update');
    Route::delete('/{room}/tables/{table}', [TableController::class, 'destroy'])->name('tables.destroy');
});

// Groupe de routes pour les commandes
Route::prefix('orders')->name('orders.')->group(function () {
    Route::post('/{order}/pay', [OrderController::class, 'markPayed'])->name('pay');
    Route::post('/{order}/steps', [OrderController::class, 'storeStep'])->name('steps.store');
    Route::post('/{order}/steps/{step}/menus', [OrderController::class, 'storeStepMenu'])->name('steps.menus.store');
    Route::post('/{order}/step-menus/{stepMenu}/cancel', [OrderController::class, 'cancelStepMenu'])->name('step-menus.cancel');
    Route::post('/{order}/step-menus/{stepMenu}/ready', [OrderController::class, 'markStepMenuReady'])->name('step-menus.ready');
    Route::post('/{order}/step-menus/{stepMenu}/served', [OrderController::class, 'markStepMenuServed'])->name('step-menus.served');
    Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
});

// Groupe de routes pour les Quick Access
Route::prefix('quick-access')->name('quick-access.')->group(function () {
    // Mise à jour en masse (positions 1..5) avec payload partiel
    Route::put('/', [QuickAccessController::class, 'update'])->name('update');
    Route::post('/reset', [QuickAccessController::class, 'reset'])->name('reset');
});
