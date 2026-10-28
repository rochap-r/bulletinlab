<?php

namespace App\Http\Resources\Api\V1\Platform;

use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'nom_espace' => $this->nom_espace,
            'slug' => $this->slug,
            'type_tenant' => $this->type_tenant,
            'statut' => $this->statut,
            'espace_parents_actif' => (bool) $this->espace_parents_actif,
            'valide_at' => $this->valide_at?->toIso8601String(),
            'valide_par_id' => $this->valide_par_id,
            'valide_par' => $this->whenLoaded('validePar', fn (): ?array => $this->validePar === null ? null : [
                'id' => $this->validePar->id,
                'nom' => $this->validePar->nom,
                'email' => $this->validePar->email,
            ]),
            'motif_refus' => $this->motif_refus,
            'administrateur' => $this->whenLoaded('utilisateurs', function (): ?array {
                /** @var Utilisateur|null $admin */
                $admin = $this->utilisateurs->first();

                if ($admin === null) {
                    return null;
                }

                return [
                    'id' => $admin->id,
                    'nom' => $admin->nom,
                    'telephone' => $admin->telephone,
                    'email' => $admin->email,
                    'roles' => $admin->tenantRoleCodes(),
                ];
            }),
            'audits' => AuditLogResource::collection($this->whenLoaded('audits')),
            'etablissements_count' => $this->whenCounted('etablissements'),
            'etablissements' => EtablissementResource::collection($this->whenLoaded('etablissements')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
