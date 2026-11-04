<?php

use App\Http\Controllers\Api\V1\Platform\ListPasswordResetRequestsController;
use App\Http\Controllers\Api\V1\Platform\ResetRequestedPasswordController;
use App\Http\Controllers\Api\V1\Platform\ResponsableMonitoringController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformLoginController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformLogoutAllController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformLogoutController;
use App\Http\Controllers\Api\V1\PlatformAuth\PlatformMeController;
use Illuminate\Support\Facades\Route;

Route::post('platform/auth/login', PlatformLoginController::class)
    ->middleware('throttle:platform.auth.login')
    ->name('platform.auth.login');

Route::middleware(['auth.cookie', 'auth:sanctum', 'platform', 'throttle:api'])->group(function (): void {
    Route::post('platform/auth/logout', PlatformLogoutController::class)
        ->name('platform.auth.logout');

    Route::post('platform/auth/logout-all', PlatformLogoutAllController::class)
        ->name('platform.auth.logout-all');

    Route::get('platform/me', PlatformMeController::class)
        ->name('platform.me');

    Route::get('platform/responsables/monitoring', ResponsableMonitoringController::class)
        ->name('platform.responsables.monitoring');

    Route::get('platform/password-reset-requests', ListPasswordResetRequestsController::class)
        ->name('platform.password-reset-requests.index');

    Route::post('platform/password-reset-requests/{demande}/reset', ResetRequestedPasswordController::class)
        ->name('platform.password-reset-requests.reset');

    require __DIR__.'/platform-config.php';
});
