<?php

use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableChildrenController;
use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableEnfantEvaluationsController;
use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableLoginController;
use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableLogoutAllController;
use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableLogoutController;
use App\Http\Controllers\Api\V1\ResponsableAuth\ResponsableMeController;
use App\Http\Controllers\Api\V1\ResponsableAuth\SetInitialResponsablePasswordController;
use App\Http\Middleware\MonitorResponsableRead;
use App\Http\Middleware\RejectClientManagedContext;
use Illuminate\Support\Facades\Route;

Route::prefix('responsables')
    ->name('responsables.')
    ->middleware(RejectClientManagedContext::class)
    ->group(function (): void {
        Route::post('auth/login', ResponsableLoginController::class)
            ->middleware('throttle:responsable.auth.login')
            ->name('auth.login');

        Route::middleware([
            'auth.cookie',
            'auth:sanctum',
            'responsable',
            'responsable.password.change',
            'throttle:api',
        ])->group(function (): void {
            Route::get('me', ResponsableMeController::class)->name('me.show');

            Route::post(
                'me/password-initial',
                SetInitialResponsablePasswordController::class,
            )->middleware('throttle:responsable.password')
                ->name('me.password.initial.store');

            Route::get('enfants', ResponsableChildrenController::class)
                ->middleware(MonitorResponsableRead::class)
                ->name('children.index');

            Route::get(
                'enfants/{lien}/evaluations',
                ResponsableEnfantEvaluationsController::class,
            )->middleware(MonitorResponsableRead::class)
                ->name('children.evaluations');

            Route::post('auth/logout', ResponsableLogoutController::class)
                ->name('auth.logout');

            Route::post('auth/logout-all', ResponsableLogoutAllController::class)
                ->name('auth.logout-all');
        });
    });
