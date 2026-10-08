<?php

use App\Http\Controllers\Api\V1\BulletinController;
use Illuminate\Support\Facades\Route;

Route::get(
    'bulletins/{bulletin}/periodes/{periode}/apercu-calcul',
    [BulletinController::class, 'previewCalculation'],
)
    ->whereNumber('bulletin')
    ->whereNumber('periode')
    ->name('bulletins.periodes.apercu-calcul.show');
Route::post(
    'bulletins/{bulletin}/periodes/{periode}/publier',
    [BulletinController::class, 'publishCalculation'],
)
    ->whereNumber('bulletin')
    ->whereNumber('periode')
    ->name('bulletins.periodes.publier.store');
Route::post(
    'bulletins/{bulletin}/periodes/{periode}/rectifications',
    [BulletinController::class, 'rectifyPublication'],
)
    ->whereNumber('bulletin')
    ->whereNumber('periode')
    ->name('bulletins.periodes.rectifications.store');
Route::get('bulletins/{bulletin}', [BulletinController::class, 'show'])
    ->whereNumber('bulletin')
    ->name('bulletins.show');
Route::get('classes/{classe}/bulletins', [BulletinController::class, 'byClasse'])
    ->whereNumber('classe')
    ->name('bulletins.by-classe');
