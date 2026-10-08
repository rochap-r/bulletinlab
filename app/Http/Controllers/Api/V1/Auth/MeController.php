<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Auth\UtilisateurResource;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeController extends Controller
{
    public function __invoke(Request $request): UtilisateurResource
    {
        $utilisateur = $request->user();

        abort_unless($utilisateur instanceof Utilisateur, Response::HTTP_UNAUTHORIZED);

        return new UtilisateurResource($utilisateur);
    }
}
