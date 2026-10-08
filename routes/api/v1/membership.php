<?php

use App\Http\Controllers\Api\V1\Membership\ListInvitationsController;
use App\Http\Controllers\Api\V1\Membership\RevokeInvitationController;
use App\Http\Controllers\Api\V1\Membership\StoreInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('invitations', ListInvitationsController::class)
    ->name('invitations.index');

Route::post('invitations', StoreInvitationController::class)
    ->name('invitations.store');

Route::delete('invitations/{invitation}', RevokeInvitationController::class)
    ->name('invitations.revoke');
