<?php

namespace App\Http\Resources\Api\V1\Membership;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invitation */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'email' => $this->email,
            'etablissement_id' => $this->etablissement_id,
            'role' => [
                'code' => $this->role?->code,
                'nom' => $this->role?->nom,
            ],
            'statut' => $this->statut(),
            'expire_le' => $this->expire_at?->toIso8601String(),
            'acceptee_le' => $this->accepted_at?->toIso8601String(),
            'revoquee_le' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
