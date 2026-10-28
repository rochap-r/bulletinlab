<?php

namespace App\Http\Resources\Api\V1\ResponsableAuth;

use App\Support\AccessTokenCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResponsableAuthenticatedSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'responsable' => new ResponsableResource($this->resource->responsable),
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
                AccessTokenCookies::RESPONSABLE,
                $this->resource->accessToken,
                (int) config('responsables.session_minutes', 720),
            ));
        }
    }
}
