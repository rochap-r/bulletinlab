<?php

use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\SetInitialPasswordController;
use App\Http\Controllers\Api\V1\Auth\UpdatePasswordController;
use App\Http\Controllers\Api\V1\EtablissementController;
use App\Http\Controllers\Api\V1\Membership\EnseignantsTemplateController;
use App\Http\Controllers\Api\V1\Membership\ImportEnseignantsController;
use App\Http\Controllers\Api\V1\Membership\ImportEnseignantsExcelController;
use App\Http\Controllers\Api\V1\Membership\ImportEnseignantsExcelPreviewController;
use App\Http\Controllers\Api\V1\Membership\ImportEnseignantsPreviewController;
use App\Http\Controllers\Api\V1\Membership\ListPasswordResetRequestsController;
use App\Http\Controllers\Api\V1\Membership\RattacherUtilisateurController;
use App\Http\Controllers\Api\V1\Membership\RechercherUtilisateurController;
use App\Http\Controllers\Api\V1\Membership\ResetRequestedPasswordController;
use App\Http\Controllers\Api\V1\Membership\ResetUtilisateurPasswordController;
use App\Http\Controllers\Api\V1\Membership\StoreDirectUtilisateurController;
use App\Http\Controllers\Api\V1\Membership\UpdateUtilisateurController;
use Illuminate\Support\Facades\Route;

Route::get('me', MeController::class)->name('me.show');

Route::get('password-reset-requests', ListPasswordResetRequestsController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('password-reset-requests.index');
Route::post('password-reset-requests/{demande}/reset', ResetRequestedPasswordController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('password-reset-requests.reset');

Route::post('me/password', UpdatePasswordController::class)
    ->middleware('throttle:me.password')
    ->name('me.password.update');

Route::post('me/password-initial', SetInitialPasswordController::class)
    ->middleware('throttle:me.password')
    ->name('me.password.initial.store');

Route::get('etablissements', [EtablissementController::class, 'index'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.index');
Route::post('etablissements', [EtablissementController::class, 'store'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.store');
Route::post('etablissements/complexe', [EtablissementController::class, 'storeComplexe'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.complexe.store');
Route::get('etablissements/{etablissement}', [EtablissementController::class, 'show'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.show');
Route::get('etablissements/{etablissement}/membres', [EtablissementController::class, 'membres'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.membres.index');
Route::get('etablissements/{etablissement}/statistiques', [EtablissementController::class, 'statistiques'])->middleware(['tenant.validated', 'require.password.change'])->name('etablissements.statistiques.show');

Route::post('utilisateurs', StoreDirectUtilisateurController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.store');

Route::post('utilisateurs/import-enseignants', ImportEnseignantsController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.import-enseignants');

Route::post('utilisateurs/import-enseignants/aperçu', ImportEnseignantsPreviewController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.import-enseignants.preview');

Route::post('utilisateurs/import-enseignants-excel', ImportEnseignantsExcelController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.import-enseignants-excel');

Route::post('utilisateurs/import-enseignants-excel/aperçu', ImportEnseignantsExcelPreviewController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.import-enseignants-excel.preview');

Route::get('utilisateurs/import-enseignants/modele', EnseignantsTemplateController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.import-enseignants.modele');

Route::match(['put', 'patch'], 'utilisateurs/{utilisateur}', UpdateUtilisateurController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.update');

Route::post('utilisateurs/{utilisateur}/reset-password', ResetUtilisateurPasswordController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.reset-password');

Route::get('utilisateurs/rechercher', RechercherUtilisateurController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.rechercher');

Route::post('utilisateurs/rattacher', RattacherUtilisateurController::class)
    ->middleware(['tenant.validated', 'require.password.change'])
    ->name('utilisateurs.rattacher');
