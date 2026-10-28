<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Domains\Responsable\Contracts\ResponsableAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResponsableAuth\SetInitialResponsablePasswordRequest;
use App\Http\Resources\Api\V1\ResponsableAuth\ResponsableAuthenticatedSessionResource;
use App\Models\Responsable;
use Symfony\Component\HttpFoundation\Response;

final class SetInitialResponsablePasswordController extends Controller
{
    public function __construct(
        private ResponsableAuthServiceInterface $authService,
    ) {}

    public function __invoke(
        SetInitialResponsablePasswordRequest $request,
    ): ResponsableAuthenticatedSessionResource {
        $responsable = $request->user();
        abort_unless($responsable instanceof Responsable, Response::HTTP_UNAUTHORIZED);

        return new ResponsableAuthenticatedSessionResource(
            $this->authService->setInitialPassword(
                $responsable,
                $request->string('mot_de_passe')->toString(),
            ),
        );
    }
}
