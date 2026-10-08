<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    public function __construct(
        private AuthServiceInterface $authService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $utilisateur = $request->user();

        abort_unless($utilisateur instanceof Utilisateur, Response::HTTP_UNAUTHORIZED);

        $this->authService->logout($utilisateur);

        return response()->noContent();
    }
}
