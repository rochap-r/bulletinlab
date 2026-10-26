<?php

namespace App\Http\Resources\Api\V1\PlatformAuth;

use App\Support\AccessTokenCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformAuthenticatedSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'user' => new PlatformUtilisateurResource($this->resource->utilisateur),
        ];

        if (! AccessTokenCookies::usesCookieTransport($request)) {
            $data['access_token'] = $this->resource->accessToken;
            $data['token_type'] = 'Bearer';
        }

        return $data;
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        if (AccessTokenCookies::usesCookieTransport($request)) {
            $response->headers->setCookie(AccessTokenCookies::make(
                $request,
                AccessTokenCookies::PLATFORM,
                $this->resource->accessToken,
            ));
        }
    }
}
