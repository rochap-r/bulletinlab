<?php

use App\Http\Controllers\Api\V1\Membership\AcceptInvitationController;
use App\Http\Controllers\Api\V1\Onboarding\RegisterTenantController;
use App\Http\Controllers\Api\V1\Public\MembershipsController;
use Illuminate\Support\Facades\Route;

Route::post('public/invitations/acceptation', AcceptInvitationController::class)
    ->middleware('throttle:public.invitation.acceptation')
    ->name('public.invitations.acceptation.store');

Route::post('public/tenants', RegisterTenantController::class)
    ->middleware('throttle:public.tenant.registration')
    ->name('public.tenants.store');

Route::get('public/memberships', MembershipsController::class)
    ->middleware('throttle:public.memberships')
    ->name('public.memberships.index');
