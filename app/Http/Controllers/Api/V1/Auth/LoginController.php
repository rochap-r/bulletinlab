<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;

class LoginController extends Controller
{
    public function __construct(
        private AuthServiceInterface $authService,
    ) {}

    public function __invoke(LoginRequest $request): AuthenticatedSessionResource
    {
        return new AuthenticatedSessionResource(
            $this->authService->login($request->loginData()),
        );
    }
}
