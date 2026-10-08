<?php

namespace App\Http\Controllers\Api\V1\PlatformAuth;

use App\Domains\Auth\Contracts\PlatformAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\UtilisateurPlateforme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformLogoutController extends Controller
{
    public function __construct(
        private PlatformAuthServiceInterface $platformAuthService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $utilisateur = $request->user();

        abort_unless($utilisateur instanceof UtilisateurPlateforme, Response::HTTP_UNAUTHORIZED);

        $this->platformAuthService->logout($utilisateur);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
