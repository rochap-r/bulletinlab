<?php

use App\Http\Controllers\Api\V1\PlatformAuth\PlatformLoginController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformLogoutController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformMeController;
use Illuminate\Support\Facades\Route;

Route::post('platform/auth/login', PlatformLoginController::class)
    ->middleware('throttle:platform.auth.login')
    ->name('platform.auth.login');

Route::middleware(['auth:sanctum', 'platform'])->group(function (): void {
    Route::post('platform/auth/logout', PlatformLogoutController::class)
        ->name('platform.auth.logout');

    Route::get('platform/me', PlatformMeController::class)
        ->name('platform.me');

    require __DIR__.'/platform-config.php';
});
