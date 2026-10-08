<?php

namespace App\Http\Resources\Api\V1\Onboarding;

use App\Domains\Tenant\DTOs\RegisteredTenantResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisteredTenantResource extends JsonResource
{
    public function __construct(private readonly RegisteredTenantResult $registration)
    {
        parent::__construct($registration);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->registration->tenant;
        $etablissement = $this->registration->etablissement;
        $utilisateur = $this->registration->utilisateur;

        return [
            'tenant' => [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'nom_espace' => $tenant->nom_espace,
                'slug' => $tenant->slug,
                'type_tenant' => $tenant->type_tenant,
                'statut' => $tenant->statut,
            ],
            'etablissement' => [
                'id' => $etablissement->id,
                'nom' => $etablissement->nom,
            ],
            'administrateur' => [
                'id' => $utilisateur->id,
                'email' => $utilisateur->email,
                'telephone' => $utilisateur->telephone,
            ],
        ];
    }
}
