<?php

namespace App\Http\Resources\Api\V1;

use App\Domains\Bulletin\DTOs\PublishBulletinResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

final class BulletinPublicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof PublishBulletinResult) {
            throw new LogicException(
                'The report card publication result is invalid.',
            );
        }

        return [
            'official' => true,
            'persisted' => true,
            'engine_version' => '1.0.0',
            ...$this->resource->toArray(),
        ];
    }
}
