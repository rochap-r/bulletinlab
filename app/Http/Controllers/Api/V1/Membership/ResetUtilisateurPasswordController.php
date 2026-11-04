<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\DirectUtilisateurService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\ResetUtilisateurPasswordRequest;
use App\Http\Resources\Api\V1\Membership\UtilisateurMotDePasseReinitialiseResource;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class ResetUtilisateurPasswordController extends Controller
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    public function __invoke(
        ResetUtilisateurPasswordRequest $request,
        Utilisateur $utilisateur,
    ): JsonResponse {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        $reinitialise = $this->service->resetPassword($utilisateur, $actor);

        return UtilisateurMotDePasseReinitialiseResource::make($reinitialise)->response()
            ->header('Cache-Control', 'no-store, private');
    }
}
