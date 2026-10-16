<?php

use App\Http\Controllers\Api\V1\ImportMyCourseAssignmentNotesController;
use App\Http\Controllers\Api\V1\MyClassController;
use App\Http\Controllers\Api\V1\MyCourseAssignmentController;
use App\Http\Controllers\Api\V1\MyCourseAssignmentGradeSheetController;
use App\Http\Controllers\Api\V1\MyCourseAssignmentSlotController;
use App\Http\Controllers\Api\V1\UpsertMyCourseAssignmentNoteController;
use Illuminate\Support\Facades\Route;

Route::get('me/classes', MyClassController::class)
    ->name('me.classes.index');
Route::get('me/attributions', MyCourseAssignmentController::class)
    ->name('me.attributions.index');
Route::get(
    'me/attributions/{attribution}/creneaux',
    MyCourseAssignmentSlotController::class,
)->name('me.attributions.creneaux.index');
Route::get(
    'me/attributions/{attribution}/creneaux/{creneau}/feuille-notes',
    MyCourseAssignmentGradeSheetController::class,
)->name('me.attributions.creneaux.feuille-notes.show');
Route::post(
    'me/attributions/{attribution}/creneaux/{creneau}/imports-notes',
    ImportMyCourseAssignmentNotesController::class,
)->middleware('periode.ouverte')->name('me.attributions.creneaux.imports-notes.store');
Route::put(
    'me/attributions/{attribution}/creneaux/{creneau}/inscriptions/{inscription}/note',
    UpsertMyCourseAssignmentNoteController::class,
)->middleware('periode.ouverte')->name('me.attributions.creneaux.inscriptions.note.update');
