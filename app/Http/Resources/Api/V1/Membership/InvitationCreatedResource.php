<?php

namespace App\Http\Resources\Api\V1\Membership;

use App\Domains\Membership\DTOs\CreatedInvitationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreatedInvitationResult */
final class InvitationCreatedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource->invitation;

        return [
            'id' => $invitation->getKey(),
            'email' => $invitation->email,
            'etablissement_id' => $invitation->etablissement_id,
            'role' => [
                'code' => $invitation->role?->code,
                'nom' => $invitation->role?->nom,
            ],
            'statut' => $invitation->statut(),
            'token' => $this->resource->token,
            'expire_le' => $invitation->expire_at?->toIso8601String(),
        ];
    }
}
