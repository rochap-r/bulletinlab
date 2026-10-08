<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UtilisateurResource($this->resource->utilisateur),
            'access_token' => $this->resource->accessToken,
            'token_type' => 'Bearer',
        ];
    }
}
