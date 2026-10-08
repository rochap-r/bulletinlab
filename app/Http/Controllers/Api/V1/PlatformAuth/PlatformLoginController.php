<?php

namespace App\Http\Controllers\Api\V1\PlatformAuth;

use App\Domains\Auth\Contracts\PlatformAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PlatformAuth\PlatformLoginRequest;
use App\Http\Resources\Api\V1\PlatformAuth\PlatformAuthenticatedSessionResource;

class PlatformLoginController extends Controller
{
    public function __construct(
        private PlatformAuthServiceInterface $platformAuthService,
    ) {}

    public function __invoke(PlatformLoginRequest $request): PlatformAuthenticatedSessionResource
    {
        return new PlatformAuthenticatedSessionResource(
            $this->platformAuthService->login($request->loginData()),
        );
    }
}
