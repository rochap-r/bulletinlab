<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Contracts\InvitationServiceInterface;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\StoreInvitationRequest;
use App\Http\Resources\Api\V1\Membership\InvitationCreatedResource;
use Illuminate\Http\JsonResponse;

final class StoreInvitationController extends Controller
{
    public function __construct(
        private readonly InvitationServiceInterface $invitationService,
        private readonly TenantContext $tenantContext,
        private readonly EstablishmentContext $establishmentContext,
    ) {}

    public function __invoke(StoreInvitationRequest $request): JsonResponse
    {
        $result = $this->invitationService->create(
            $request->invitationData(),
            $this->tenantContext->current(),
            $this->establishmentContext->current(),
            $request->actor(),
        );

        return (new InvitationCreatedResource($result))
            ->response()
            ->setStatusCode(201);
    }
}
