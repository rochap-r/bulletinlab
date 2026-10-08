<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Contracts\InvitationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\AcceptInvitationRequest;
use App\Http\Resources\Api\V1\Membership\OnboardedUtilisateurResource;
use Illuminate\Http\JsonResponse;

final class AcceptInvitationController extends Controller
{
    public function __construct(private readonly InvitationServiceInterface $invitationService) {}

    public function __invoke(AcceptInvitationRequest $request): JsonResponse
    {
        $utilisateur = $this->invitationService->accept(
            $request->acceptanceData(),
        );

        return OnboardedUtilisateurResource::make($utilisateur)
            ->response()
            ->setStatusCode(201);
    }
}
