<?php

namespace App\Http\Controllers\Api\V1\PlatformAuth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformAuth\PlatformUtilisateurResource;
use App\Models\UtilisateurPlateforme;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformMeController extends Controller
{
    public function __invoke(Request $request): PlatformUtilisateurResource
    {
        $utilisateur = $request->user();

        abort_unless($utilisateur instanceof UtilisateurPlateforme, Response::HTTP_UNAUTHORIZED);

        return new PlatformUtilisateurResource($utilisateur);
    }
}
