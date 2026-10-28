<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/v1/auth.php';
require __DIR__.'/v1/platform.php';
require __DIR__.'/v1/public.php';
require __DIR__.'/v1/responsables.php';

Route::middleware(['auth.cookie', 'auth:sanctum', 'throttle:api', 'tenant'])->group(function (): void {
    require __DIR__.'/v1/establishments.php';

    Route::middleware(['tenant.validated', 'require.password.change'])->group(function (): void {
        require __DIR__.'/v1/annual.php';

        Route::middleware('establishment')->group(function (): void {
            require __DIR__.'/v1/classrooms.php';
            require __DIR__.'/v1/teachers.php';
            require __DIR__.'/v1/students.php';
            require __DIR__.'/v1/evaluations.php';
            require __DIR__.'/v1/bulletins.php';
            require __DIR__.'/v1/membership.php';
        });
    });
});
