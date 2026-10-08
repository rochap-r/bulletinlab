<?php

namespace App\Http\Resources\Api\V1;

use App\Domains\Bulletin\DTOs\BulletinCalculationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

final class BulletinCalculationPreviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BulletinCalculationResult) {
            throw new LogicException(
                'The report card calculation preview is invalid.',
            );
        }

        return [
            'official' => false,
            'persisted' => false,
            ...$this->resource->toArray(),
        ];
    }
}
