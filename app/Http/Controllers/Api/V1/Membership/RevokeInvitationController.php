<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Contracts\InvitationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\RevokeInvitationRequest;
use App\Http\Resources\Api\V1\Membership\InvitationResource;
use Illuminate\Http\JsonResponse;

final class RevokeInvitationController extends Controller
{
    public function __construct(private readonly InvitationServiceInterface $invitationService) {}

    public function __invoke(RevokeInvitationRequest $request): JsonResponse
    {
        $invitation = $this->invitationService->revoke(
            $request->invitation(),
            $request->actor(),
        );

        return InvitationResource::make($invitation)->response();
    }
}
