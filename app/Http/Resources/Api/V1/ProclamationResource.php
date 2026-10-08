<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Proclamation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProclamationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Proclamation $proclamation */
        $proclamation = $this->resource;

        return [
            'id' => $proclamation->getKey(),
            'tenant_id' => $proclamation->tenant_id,
            'etablissement_id' => $proclamation->etablissement_id,
            'annee_scolaire_id' => $proclamation->annee_scolaire_id,
            'classe_id' => $proclamation->classe_id,
            'periode_id' => $proclamation->periode_id,
            'publication_id' => $proclamation->publication_id,
            'type' => $proclamation->type,
            'statut' => $proclamation->statut,
            'date_generation' => $proclamation->date_generation?->toDateString(),
            'generee_par' => $proclamation->generee_par,
            'validee_par' => $proclamation->validee_par,
            'validee_at' => $proclamation->validee_at?->toIso8601String(),
            'pdf_path' => $proclamation->pdf_path,
            'metadata' => $proclamation->metadata,
            'created_at' => $proclamation->created_at?->toIso8601String(),
            'updated_at' => $proclamation->updated_at?->toIso8601String(),
        ];
    }
}
