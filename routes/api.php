<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [App\Http\Controllers\Api\V1\AuthController::class, 'login']);
    Route::post('/auth/register', [App\Http\Controllers\Api\V1\AuthController::class, 'register']);
    Route::post('/auth/logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::apiResource('tenants', App\Http\Controllers\Api\V1\TenantController::class)->only(['index', 'show']);
        Route::apiResource('annees-scolaires', App\Http\Controllers\Api\V1\AnneeScolaireController::class);
        Route::post('annees-scolaires/{id}/open', [App\Http\Controllers\Api\V1\AnneeScolaireController::class, 'open'])->name('annees-scolaires.open');
        Route::apiResource('classes', App\Http\Controllers\Api\V1\ClasseController::class);
        Route::post('classes/{id}/clone-template', [App\Http\Controllers\Api\V1\ClasseController::class, 'cloneTemplate'])->name('classes.clone-template');
        Route::get('classes/{id}/students', [App\Http\Controllers\Api\V1\ClasseController::class, 'students'])->name('classes.students');
        Route::get('classes/{id}/encoding-progress', [App\Http\Controllers\Api\V1\ClasseController::class, 'encodingProgress'])->name('classes.encoding-progress');
        Route::apiResource('eleves', App\Http\Controllers\Api\V1\EleveController::class);
        Route::post('eleves/import', [App\Http\Controllers\Api\V1\EleveController::class, 'import'])->name('eleves.import');
        Route::apiResource('inscriptions', App\Http\Controllers\Api\V1\InscriptionController::class);
        Route::apiResource('bulletins', App\Http\Controllers\Api\V1\BulletinController::class)->only(['index', 'show']);
        Route::get('classes/{id}/bulletins', [App\Http\Controllers\Api\V1\BulletinController::class, 'byClasse'])->name('bulletins.by-classe');
        Route::post('bulletins/calculate', [App\Http\Controllers\Api\V1\BulletinController::class, 'calculate'])->name('bulletins.calculate');
        Route::apiResource('notes', App\Http\Controllers\Api\V1\NoteController::class);
        Route::get('classes/{id}/notes', [App\Http\Controllers\Api\V1\NoteController::class, 'byClasse'])->name('notes.by-classe');
        Route::get('notes/{noteId}/history', [App\Http\Controllers\Api\V1\NoteController::class, 'history'])->name('notes.history');
        Route::post('notes/import', [App\Http\Controllers\Api\V1\NoteController::class, 'import'])->name('notes.import');
        Route::post('periods/{periodeId}/lock', [App\Http\Controllers\Api\V1\PeriodeController::class, 'lock'])->name('periods.lock');
        Route::post('periods/{periodeId}/unlock', [App\Http\Controllers\Api\V1\PeriodeController::class, 'unlock'])->name('periods.unlock');
        Route::apiResource('attendance', App\Http\Controllers\Api\V1\AttendanceController::class)->only(['index', 'store']);
        Route::get('inscriptions/{inscriptionId}/attendance', [App\Http\Controllers\Api\V1\AttendanceController::class, 'showStudent'])->name('attendance.student');
    });
});
