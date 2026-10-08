<?php

use App\Http\Controllers\Api\V1\Platform\AnneeScolaireController;
use App\Http\Controllers\Api\V1\Platform\BlocSpecialController;
use App\Http\Controllers\Api\V1\Platform\CalendrierController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationBlocController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationBulletinController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationMaximaController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationNodeController;
use App\Http\Controllers\Api\V1\Platform\ConfigurationPeriodeController;
use App\Http\Controllers\Api\V1\Platform\EtablissementController;
use App\Http\Controllers\Api\V1\Platform\LayoutBulletinController;
use App\Http\Controllers\Api\V1\Platform\Referentiel\CycleController;
use App\Http\Controllers\Api\V1\Platform\Referentiel\DegreController;
use App\Http\Controllers\Api\V1\Platform\Referentiel\MatiereController;
use App\Http\Controllers\Api\V1\Platform\Referentiel\NiveauController;
use App\Http\Controllers\Api\V1\Platform\Referentiel\OptionController;
use App\Http\Controllers\Api\V1\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform/referentiel')
    ->name('platform.referentiel.')
    ->group(function (): void {
        Route::get('cycles', [CycleController::class, 'index'])
            ->name('cycles.index');

        Route::post('cycles', [CycleController::class, 'store'])
            ->name('cycles.store');

        Route::put('cycles/{cycle}', [CycleController::class, 'update'])
            ->whereNumber('cycle')
            ->name('cycles.update');

        Route::delete('cycles/{cycle}', [CycleController::class, 'destroy'])
            ->whereNumber('cycle')
            ->name('cycles.destroy');

        Route::get('cycles/{cycle}/degres', [DegreController::class, 'index'])
            ->whereNumber('cycle')
            ->name('cycles.degres.index');

        Route::post('cycles/{cycle}/degres', [DegreController::class, 'store'])
            ->whereNumber('cycle')
            ->name('cycles.degres.store');

        Route::put('cycles/{cycle}/degres/{degre}', [DegreController::class, 'update'])
            ->whereNumber('cycle')
            ->whereNumber('degre')
            ->name('cycles.degres.update');

        Route::delete('cycles/{cycle}/degres/{degre}', [DegreController::class, 'destroy'])
            ->whereNumber('cycle')
            ->whereNumber('degre')
            ->name('cycles.degres.destroy');

        Route::get('cycles/{cycle}/niveaux', [NiveauController::class, 'index'])
            ->whereNumber('cycle')
            ->name('cycles.niveaux.index');

        Route::post('cycles/{cycle}/niveaux', [NiveauController::class, 'store'])
            ->whereNumber('cycle')
            ->name('cycles.niveaux.store');

        Route::put('cycles/{cycle}/niveaux/{niveau}', [NiveauController::class, 'update'])
            ->whereNumber('cycle')
            ->whereNumber('niveau')
            ->name('cycles.niveaux.update');

        Route::delete('cycles/{cycle}/niveaux/{niveau}', [NiveauController::class, 'destroy'])
            ->whereNumber('cycle')
            ->whereNumber('niveau')
            ->name('cycles.niveaux.destroy');

        Route::get('cycles/{cycle}/options', [OptionController::class, 'index'])
            ->whereNumber('cycle')
            ->name('cycles.options.index');

        Route::post('cycles/{cycle}/options', [OptionController::class, 'store'])
            ->whereNumber('cycle')
            ->name('cycles.options.store');

        Route::put('cycles/{cycle}/options/{option}', [OptionController::class, 'update'])
            ->whereNumber('cycle')
            ->whereNumber('option')
            ->name('cycles.options.update');

        Route::delete('cycles/{cycle}/options/{option}', [OptionController::class, 'destroy'])
            ->whereNumber('cycle')
            ->whereNumber('option')
            ->name('cycles.options.destroy');

        Route::get('matieres', [MatiereController::class, 'index'])
            ->name('matieres.index');

        Route::post('matieres', [MatiereController::class, 'store'])
            ->name('matieres.store');

        Route::put('matieres/{matiere}', [MatiereController::class, 'update'])
            ->whereNumber('matiere')
            ->name('matieres.update');

        Route::delete('matieres/{matiere}', [MatiereController::class, 'destroy'])
            ->whereNumber('matiere')
            ->name('matieres.destroy');
    });

Route::get('platform/annees-scolaires', [AnneeScolaireController::class, 'index'])
    ->name('platform.annees-scolaires.index');

Route::post('platform/annees-scolaires', [AnneeScolaireController::class, 'store'])
    ->name('platform.annees-scolaires.store');

Route::put('platform/annees-scolaires/{annee}', [AnneeScolaireController::class, 'update'])
    ->whereNumber('annee')
    ->name('platform.annees-scolaires.update');

Route::post('platform/annees-scolaires/{annee}/activation', [AnneeScolaireController::class, 'activate'])
    ->whereNumber('annee')
    ->name('platform.annees-scolaires.activation.store');

Route::post('platform/annees-scolaires/{annee}/cloture', [AnneeScolaireController::class, 'close'])
    ->whereNumber('annee')
    ->name('platform.annees-scolaires.cloture.store');

Route::get('platform/calendriers', [CalendrierController::class, 'index'])
    ->name('platform.calendriers.index');

Route::post('platform/calendriers', [CalendrierController::class, 'store'])->name('platform.calendriers.store');
Route::get('platform/calendriers/{calendrier}', [CalendrierController::class, 'show'])->whereNumber('calendrier')->name('platform.calendriers.show');
Route::put('platform/calendriers/{calendrier}', [CalendrierController::class, 'update'])->whereNumber('calendrier')->name('platform.calendriers.update');
Route::delete('platform/calendriers/{calendrier}', [CalendrierController::class, 'destroy'])->whereNumber('calendrier')->name('platform.calendriers.destroy');

Route::get('platform/configurations-bulletin', [ConfigurationBulletinController::class, 'index'])
    ->name('platform.configurations-bulletin.index');

Route::post('platform/configurations-bulletin', [ConfigurationBulletinController::class, 'store'])
    ->name('platform.configurations-bulletin.store');

Route::get('platform/configurations-bulletin/{configuration}', [ConfigurationBulletinController::class, 'show'])
    ->whereNumber('configuration')
    ->name('platform.configurations-bulletin.show');

Route::put('platform/configurations-bulletin/{configuration}', [ConfigurationBulletinController::class, 'update'])
    ->whereNumber('configuration')
    ->name('platform.configurations-bulletin.update');

Route::delete('platform/configurations-bulletin/{configuration}', [ConfigurationBulletinController::class, 'destroy'])
    ->whereNumber('configuration')
    ->name('platform.configurations-bulletin.destroy');

Route::get('platform/layouts-bulletin', [LayoutBulletinController::class, 'index'])
    ->name('platform.layouts-bulletin.index');

Route::get('platform/blocs-speciaux', [BlocSpecialController::class, 'index'])
    ->name('platform.blocs-speciaux.index');

Route::prefix('platform/configurations-bulletin/{configuration}')
    ->whereNumber('configuration')
    ->name('platform.configurations-bulletin.')
    ->group(function (): void {
        Route::post('nodes', [ConfigurationNodeController::class, 'store'])
            ->name('nodes.store');

        Route::put('nodes/{node}', [ConfigurationNodeController::class, 'update'])
            ->whereNumber('node')
            ->name('nodes.update');

        Route::delete('nodes/{node}', [ConfigurationNodeController::class, 'destroy'])
            ->whereNumber('node')
            ->name('nodes.destroy');

        Route::post('periodes', [ConfigurationPeriodeController::class, 'store'])
            ->name('periodes.store');

        Route::put('periodes/{periode}', [ConfigurationPeriodeController::class, 'update'])
            ->whereNumber('periode')
            ->name('periodes.update');

        Route::delete('periodes/{periode}', [ConfigurationPeriodeController::class, 'destroy'])
            ->whereNumber('periode')
            ->name('periodes.destroy');

        Route::put('maxima', [ConfigurationMaximaController::class, 'update'])
            ->name('maxima.update');

        Route::post('blocs', [ConfigurationBlocController::class, 'store'])
            ->name('blocs.store');

        Route::put('blocs/{templateBloc}', [ConfigurationBlocController::class, 'update'])
            ->whereNumber('templateBloc')
            ->name('blocs.update');

        Route::delete('blocs/{templateBloc}', [ConfigurationBlocController::class, 'destroy'])
            ->whereNumber('templateBloc')
            ->name('blocs.destroy');

        Route::post('validation', [ConfigurationBulletinController::class, 'validateConfiguration'])
            ->name('validation.store');

        Route::post('publication', [ConfigurationBulletinController::class, 'publish'])
            ->name('publication.store');

        Route::post('depreciation', [ConfigurationBulletinController::class, 'deprecate'])
            ->name('depreciation.store');

        Route::post('retour-brouillon', [ConfigurationBulletinController::class, 'returnToDraft'])
            ->name('retour-brouillon.store');

        Route::delete('purge', [ConfigurationBulletinController::class, 'purge'])
            ->name('purge.store');
    });

Route::prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::get('tenants', [TenantController::class, 'index'])
            ->name('tenants.index');

        Route::post('tenants', [TenantController::class, 'store'])
            ->name('tenants.store');

        Route::get('tenants/{tenant}', [TenantController::class, 'show'])
            ->whereNumber('tenant')
            ->name('tenants.show');

        Route::put('tenants/{tenant}', [TenantController::class, 'update'])
            ->whereNumber('tenant')
            ->name('tenants.update');

        Route::post('tenants/{tenant}/activation', [TenantController::class, 'activate'])
            ->whereNumber('tenant')
            ->name('tenants.activation');

        Route::post('tenants/{tenant}/refus', [TenantController::class, 'reject'])
            ->whereNumber('tenant')
            ->name('tenants.refus');

        Route::get('tenants/{tenant}/etablissements', [EtablissementController::class, 'index'])
            ->whereNumber('tenant')
            ->name('tenants.etablissements.index');

        Route::post('tenants/{tenant}/etablissements', [EtablissementController::class, 'store'])
            ->whereNumber('tenant')
            ->name('tenants.etablissements.store');

        Route::put('etablissements/{etablissement}', [EtablissementController::class, 'update'])
            ->whereNumber('etablissement')
            ->name('etablissements.update');
    });
