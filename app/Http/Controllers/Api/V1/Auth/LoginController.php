<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
use App\Support\AccessTokenCookies;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(
        private AuthServiceInterface $authService,
    ) {}

    public function __invoke(LoginRequest $request): AuthenticatedSessionResource|JsonResponse
    {
        $data = $request->loginData();

        if ($data->tenantId === null && $data->tenantSlug === null) {
            $result = $this->authService->loginGlobal($data->identifiant, $data->password);

            if ($result->session !== null) {
                return new AuthenticatedSessionResource($result->session);
            }

            $data = [
                'memberships' => $result->memberships,
            ];

            if (! AccessTokenCookies::usesCookieTransport($request)) {
                $data['select_token'] = $result->selectToken;
            }

            $response = response()->json([
                'data' => [
                    ...$data,
                ],
            ])->header('Cache-Control', 'no-store, private');

            if (AccessTokenCookies::usesCookieTransport($request)) {
                $response->headers->setCookie(AccessTokenCookies::make(
                    $request,
                    AccessTokenCookies::TENANT_SELECT,
                    (string) $result->selectToken,
                    5,
                ));
            }

            return $response;
        }

        return new AuthenticatedSessionResource(
            $this->authService->login($data),
        );
    }
}
