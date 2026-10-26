<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\SelectTenantController;
use App\Http\Controllers\Api\V1\Auth\SwitchTenantController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', LoginController::class)
    ->middleware('throttle:auth.login')
    ->name('auth.login');

Route::post('auth/select', SelectTenantController::class)
    ->middleware(['auth.cookie', 'auth:sanctum', 'throttle:api'])
    ->name('auth.select');

Route::post('auth/logout', LogoutController::class)
    ->middleware(['auth.cookie', 'auth:sanctum'])
    ->name('auth.logout');

Route::post('auth/logout-all', LogoutAllController::class)
    ->middleware(['auth.cookie', 'auth:sanctum', 'throttle:api'])
    ->name('auth.logout-all');

Route::post('auth/switch', SwitchTenantController::class)
    ->middleware(['auth.cookie', 'auth:sanctum', 'throttle:api', 'tenant'])
    ->name('auth.switch');
