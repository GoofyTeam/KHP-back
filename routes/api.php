<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RestaurantCardController;
use App\Models\Company;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/register', [AuthController::class, 'register'])->name('register');

    Route::post('password/send/resetpassword', [AuthController::class, 'sendEmailToken'])->name('email_token');
    Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password_reset');

    Route::get('/restaurant-card/{public_menu_card_url}', [RestaurantCardController::class, 'show'])->name('restaurant-card.show');

    Route::get('/public/image-proxy/{public_menu_card_url}/{bucket}/{path}', function (Request $request, string $public_menu_card_url, string $bucket, string $path) {
        $normalizedBucket = trim($bucket, '/');
        $normalizedPath = ltrim($path, '/');

        if ($normalizedBucket === '' || str_contains($normalizedBucket, '..') || str_contains($normalizedPath, '..')) {
            abort(404);
        }

        $fullPath = $normalizedBucket.'/'.$normalizedPath;

        $company = Company::where('public_menu_card_url', $public_menu_card_url)->firstOrFail();

        if (! $company->show_menu_images) {
            abort(404);
        }

        $hasMenuImage = Menu::query()
            ->where('company_id', $company->id)
            ->where('image_url', $fullPath)
            ->exists();

        if (! $hasMenuImage) {
            abort(404);
        }

        try {
            $disk = Storage::disk('s3');

            if (! $disk->exists($fullPath)) {
                abort(404);
            }

            $temporaryUrl = $disk->temporaryUrl($fullPath, now()->addMinutes(5));
            $contents = file_get_contents($temporaryUrl);
            $mimeType = $disk->mimeType($fullPath) ?: 'image/jpeg';
        } catch (\Throwable $e) {
            abort(404);
        }

        return response($contents)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=300');
    })->where('path', '.*')->name('public-image-proxy');

    Route::middleware('auth')->group(function () {
        include base_path('routes/authed_route.php');
    });

});
