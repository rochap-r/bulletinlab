<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', LoginController::class)
    ->middleware('throttle:auth.login')
    ->name('auth.login');

Route::post('auth/logout', LogoutController::class)
    ->middleware('auth:sanctum')
    ->name('auth.logout');
