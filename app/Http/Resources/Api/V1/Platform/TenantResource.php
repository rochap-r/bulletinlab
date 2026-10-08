<?php

namespace App\Http\Resources\Api\V1\Platform;

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
            'valide_at' => $this->valide_at?->toIso8601String(),
            'valide_par_id' => $this->valide_par_id,
            'motif_refus' => $this->motif_refus,
            'etablissements_count' => $this->whenCounted('etablissements'),
            'etablissements' => EtablissementResource::collection($this->whenLoaded('etablissements')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
