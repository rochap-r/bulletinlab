<?php

namespace App\Http\Resources\Api\V1\Membership;

use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Utilisateur */
final class OnboardedUtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'actif' => (bool) $this->actif,
            'roles' => $this->roles->pluck('code')->values()->all(),
        ];
    }
}
