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
                'saisissable' => (bool) $this->periode->saisissable,
                'calculable' => (bool) $this->periode->calculable,
                'import_code' => $this->importCode((string) $this->periode->code),
            ]),
            'maximum' => $this->maxima,
            'coefficient' => $this->coefficient,
            'verrouille' => $isLocked,
            'saisie_autorisee' => (bool) $this->periode->saisissable
                && ! $isLocked
                && (bool) ($this->periode_ouverte ?? true),
        ];
    }

    private function importCode(string $code): string
    {
        if (preg_match('/^EXAM_S(\d+)$/i', $code, $matches) === 1) {
            return 'EXAM'.$matches[1];
        }

        if (preg_match('/^TOTAL_S(\d+)$/i', $code, $matches) === 1) {
            return 'TOT'.$matches[1];
        }

        return strtoupper($code) === 'TOTAL_GENERAL' ? 'TOTG' : $code;
    }
}
