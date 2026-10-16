<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'nouvelles_valeurs' => $this->nouvelles_valeurs,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
