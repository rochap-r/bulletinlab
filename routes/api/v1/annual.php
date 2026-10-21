<?php

use App\Http\Controllers\Api\V1\AnneeScolaireController;
use App\Http\Controllers\Api\V1\OuvertureAnnuelleController;
use App\Http\Controllers\Api\V1\SchoolCalendarAlertController;
use App\Http\Controllers\Api\V1\SchoolCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('annees-scolaires/courante', [AnneeScolaireController::class, 'courante'])
    ->name('annees-scolaires.courante.show');

Route::get('annees-scolaires', [AnneeScolaireController::class, 'index'])
    ->name('annees-scolaires.index');

Route::get('pilotage/alertes', SchoolCalendarAlertController::class)
    ->name('pilotage.alertes.index');

Route::get('pilotage/calendrier', SchoolCalendarController::class)
    ->name('pilotage.calendrier.show');

Route::get('annees-scolaires/{annees_scolaire}', [AnneeScolaireController::class, 'show'])
    ->whereNumber('annees_scolaire')
    ->name('annees-scolaires.show');

Route::post('ouvertures-annuelles', [OuvertureAnnuelleController::class, 'store'])
    ->name('ouvertures-annuelles.store');

Route::post('ouvertures-annuelles/{ouverture_annuelle}/cloture', [OuvertureAnnuelleController::class, 'cloture'])
    ->whereNumber('ouverture_annuelle')
    ->name('ouvertures-annuelles.cloture.store');
