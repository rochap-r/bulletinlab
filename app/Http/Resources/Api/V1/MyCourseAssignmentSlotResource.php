<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MyCourseAssignmentSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isLocked = (bool) $this->verrouille;

        return [
            'id' => $this->id,
            'attribution_id' => $this->resource->assignment_id,
            'periode' => $this->whenLoaded('periode', fn (): array => [
                'id' => $this->periode->id,
                'code' => $this->periode->code,
                'nom' => $this->periode->nom,
                'type' => $this->periode->type_periode,
                'ordre' => $this->periode->ordre,
            ]),
            'maximum' => $this->maxima,
            'coefficient' => $this->coefficient,
            'verrouille' => $isLocked,
            'saisie_autorisee' => ! $isLocked,
        ];
    }
}
