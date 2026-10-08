<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\ClasseController;
use Illuminate\Support\Facades\Route;

Route::apiResource('classes', ClasseController::class)
    ->parameters(['classes' => 'classe'])
    ->only(['index', 'store', 'show', 'update']);
Route::post('classes/{classe}/archivage', [ClasseController::class, 'archive'])
    ->name('classes.archivage.store');
Route::get('classes/{classe}/cours', [ClasseController::class, 'courses'])
    ->name('classes.cours.index');
Route::put(
    'classes/{classe}/cours/{node}/attribution',
    [ClasseController::class, 'assignCourseTeacher'],
)->name('classes.cours.attribution.update');
Route::put(
    'classes/{classe}/titulariat',
    [ClasseController::class, 'assignTitular'],
)->name('classes.titulariat.update');
Route::get(
    'classes/{classe}/periodes/{periode}/progression-encodage',
    [ClasseController::class, 'encodingProgress'],
)->name('classes.periodes.encoding-progress');
Route::post(
    'classes/{classe}/periodes/{periode}/proclamations',
    [ClasseController::class, 'generateProclamation'],
)
    ->whereNumber('classe')
    ->whereNumber('periode')
    ->name('classes.periodes.proclamations.store');
Route::post(
    'classes/{classe}/periodes/{periode}/deliberations',
    [ClasseController::class, 'deliberate'],
)
    ->whereNumber('classe')
    ->whereNumber('periode')
    ->name('classes.periodes.deliberations.store');
Route::post(
    'classes/{classe}/periodes/{periode}/publications',
    [ClasseController::class, 'publishClass'],
)
    ->whereNumber('classe')
    ->whereNumber('periode')
    ->name('classes.periodes.publications.store');
Route::post(
    'classes/{classe}/periodes/{periode}/promotions',
    [ClasseController::class, 'generatePromotion'],
)
    ->whereNumber('classe')
    ->whereNumber('periode')
    ->name('classes.periodes.promotions.store');

Route::get('classes/{classe}/presences', [AttendanceController::class, 'index'])
    ->name('classes.presences.index');
Route::post('classes/{classe}/presences', [AttendanceController::class, 'store'])
    ->name('classes.presences.store');
Route::get('inscriptions/{inscription}/presences', [AttendanceController::class, 'showStudent'])
    ->name('inscriptions.presences.index');
