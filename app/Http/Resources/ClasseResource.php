<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'etablissement_id' => $this->etablissement_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'cycle_id' => $this->cycle_id,
            'degre_id' => $this->degre_id,
            'niveau_id' => $this->niveau_id,
            'option_id' => $this->option_id,
            'system_template_id' => $this->system_template_id,
            'parallele' => $this->parallele,
            'nom' => $this->nom,
            'code' => $this->code,
            'titulaire_id' => $this->titulaire_id,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
