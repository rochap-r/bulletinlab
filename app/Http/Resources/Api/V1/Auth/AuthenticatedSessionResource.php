<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Support\AccessTokenCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'user' => new UtilisateurResource($this->resource->utilisateur),
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
                AccessTokenCookies::TENANT,
                $this->resource->accessToken,
            ));
            $response->headers->setCookie(AccessTokenCookies::forget(AccessTokenCookies::TENANT_SELECT));
        }
    }
}
