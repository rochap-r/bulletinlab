<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Domains\Responsable\Contracts\ResponsableAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResponsableAuth\ResponsableLoginRequest;
use App\Http\Resources\Api\V1\ResponsableAuth\ResponsableAuthenticatedSessionResource;

final class ResponsableLoginController extends Controller
{
    public function __construct(
        private ResponsableAuthServiceInterface $authService,
    ) {}

    public function __invoke(
        ResponsableLoginRequest $request,
    ): ResponsableAuthenticatedSessionResource {
        return new ResponsableAuthenticatedSessionResource(
            $this->authService->login(
                $request->telephone(),
                $request->string('mot_de_passe')->toString(),
            ),
        );
    }
}
