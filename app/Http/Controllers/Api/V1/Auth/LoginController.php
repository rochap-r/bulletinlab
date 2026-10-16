<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
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

            return response()->json([
                'data' => [
                    'memberships' => $result->memberships,
                    'select_token' => $result->selectToken,
                ],
            ])->header('Cache-Control', 'no-store, private');
        }

        return new AuthenticatedSessionResource(
            $this->authService->login($data),
        );
    }
}
