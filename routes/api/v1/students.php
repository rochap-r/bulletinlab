<?php

use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\EleveController;
use App\Http\Controllers\Api\V1\InscriptionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('eleves', EleveController::class)
    ->parameters(['eleves' => 'eleve'])
    ->only(['index', 'store', 'show', 'update']);
Route::post('eleves/{eleve}/archivage', [EleveController::class, 'archive'])
    ->name('eleves.archivage.store');
Route::post(
    'eleves/{eleve}/reactivation',
    [EleveController::class, 'reactivate'],
)->name('eleves.reactivation.store');

Route::get('classes/{classe}/inscriptions', [InscriptionController::class, 'index'])
    ->name('classes.inscriptions.index');
Route::post('classes/{classe}/inscriptions', [InscriptionController::class, 'store'])
    ->name('classes.inscriptions.store');
Route::get('inscriptions/{inscription}', [InscriptionController::class, 'show'])
    ->name('inscriptions.show');
Route::post(
    'inscriptions/{inscription}/sorties',
    [InscriptionController::class, 'recordExit'],
)->name('inscriptions.sorties.store');
Route::get(
    'inscriptions/{inscription}/bulletin',
    [BulletinController::class, 'showForEnrollment'],
)->name('inscriptions.bulletin.show');
