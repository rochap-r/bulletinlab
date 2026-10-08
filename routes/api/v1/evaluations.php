<?php

use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\PeriodeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('notes', NoteController::class)
    ->only(['index', 'show'])
    ->where(['note' => '[0-9]+']);
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
