<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\CourseAssignmentSpreadsheetController;
use App\Http\Controllers\Api\V1\ReferentielController;
use App\Http\Controllers\Api\V1\TitularAssignmentController;
use Illuminate\Support\Facades\Route;

Route::get('referentiels', [ReferentielController::class, 'index'])
    ->name('referentiels.index');

Route::post('classes/organisation', [ClasseController::class, 'organize'])
    ->name('classes.organisation.store');
Route::put('titulariats', [TitularAssignmentController::class, 'save'])->name('titulariats.update');
Route::get('titulariats/excel/modele', [TitularAssignmentController::class, 'template'])->name('titulariats.excel.modele');
Route::post('titulariats/excel/apercu', [TitularAssignmentController::class, 'preview'])->name('titulariats.excel.apercu');

Route::apiResource('classes', ClasseController::class)
    ->parameters(['classes' => 'classe'])
    ->only(['index', 'store', 'show', 'update']);
Route::post('classes/{classe}/archivage', [ClasseController::class, 'archive'])
    ->name('classes.archivage.store');
Route::get('classes/{classe}/cours', [ClasseController::class, 'courses'])
    ->name('classes.cours.index');
Route::get('classes/{classe}/attributions-excel/modele', [CourseAssignmentSpreadsheetController::class, 'template'])
    ->whereNumber('classe')->name('classes.attributions-excel.modele');
Route::post('classes/{classe}/attributions-excel/apercu', [CourseAssignmentSpreadsheetController::class, 'preview'])
    ->whereNumber('classe')->name('classes.attributions-excel.apercu');
Route::put('classes/{classe}/equipe-pedagogique', [ClasseController::class, 'saveTeachingTeam'])
    ->whereNumber('classe')
    ->name('classes.equipe-pedagogique.update');
Route::post('classes/{classe}/configuration-cours', [ClasseController::class, 'initializeCourses'])
    ->whereNumber('classe')
    ->name('classes.configuration-cours.store');
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
