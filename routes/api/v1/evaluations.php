<?php

use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\PeriodeController;
use App\Http\Controllers\Api\V1\Teacher\EvaluationController;
use App\Http\Controllers\Api\V1\Teacher\EvaluationResultatController;
use App\Http\Controllers\Api\V1\Teacher\PublishEvaluationController;
use App\Http\Controllers\Api\V1\Teacher\TypeEvaluationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('notes', NoteController::class)
    ->only(['index', 'show'])
    ->where(['note' => '[0-9]+']);

Route::get('types-evaluations', TypeEvaluationController::class)
    ->name('types-evaluations.index');
Route::get('evaluations', [EvaluationController::class, 'index'])
    ->name('evaluations.index');
Route::post('evaluations', [EvaluationController::class, 'store'])
    ->name('evaluations.store');
Route::get('evaluations/{evaluation}', [EvaluationController::class, 'show'])
    ->name('evaluations.show')
    ->whereNumber('evaluation');
Route::post('evaluations/{evaluation}/publication', PublishEvaluationController::class)
    ->name('evaluations.publication.store')
    ->whereNumber('evaluation');
Route::post(
    'evaluations/{evaluation}/resultats',
    [EvaluationResultatController::class, 'store'],
)
    ->name('evaluations.resultats.store')
    ->whereNumber('evaluation');
Route::get('classes/{classe}/notes', [NoteController::class, 'byClasse'])
    ->whereNumber('classe')
    ->name('notes.by-classe');
Route::get('notes/{note}/historique', [NoteController::class, 'history'])
    ->whereNumber('note')
    ->name('notes.history');

Route::post(
    'classes/{classe}/periodes/{periode}/verrouillages',
    [PeriodeController::class, 'lock'],
)->name('classes.periodes.verrouillages.store');
Route::post(
    'classes/{classe}/periodes/{periode}/reouvertures',
    [PeriodeController::class, 'reopen'],
)->name('classes.periodes.reouvertures.store');
