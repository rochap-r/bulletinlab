<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\InvitationIndexRequest;
use App\Http\Resources\Api\V1\Membership\InvitationResource;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;

final class ListInvitationsController extends Controller
{
    public function __construct(private readonly EstablishmentContext $establishmentContext) {}

    public function __invoke(InvitationIndexRequest $request): JsonResponse
    {
        $invitations = Invitation::query()
            ->where('tenant_id', $request->actor()->tenant_id)
            ->where('etablissement_id', $this->establishmentContext->id())
            ->with(['role', 'createdBy'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        return InvitationResource::collection($invitations)->response();
    }
}
