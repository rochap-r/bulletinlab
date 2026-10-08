<?php

use App\Http\Controllers\Api\V1\Membership\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Onboarding\RegisterTenantController;
use Illuminate\Support\Facades\Route;

Route::post('public/invitations/acceptation', AcceptInvitationController::class)
    ->middleware('throttle:public.invitation.acceptation')
    ->name('public.invitations.acceptation.store');

Route::post('public/tenants', RegisterTenantController::class)
    ->middleware('throttle:public.tenant.registration')
    ->name('public.tenants.store');
