<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\Auth\Services\DemandeReinitialisationMotDePasseService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Platform\PasswordResetRequestResource;
use App\Models\DemandeReinitialisationMotDePasse;
use App\Models\UtilisateurPlateforme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResetRequestedPasswordController extends Controller
{
    public function __construct(
        private DemandeReinitialisationMotDePasseService $service,
    ) {}

    public function __invoke(
        Request $request,
        DemandeReinitialisationMotDePasse $demande,
    ): JsonResponse {
        $acteur = $request->user();
        abort_unless($acteur instanceof UtilisateurPlateforme, Response::HTTP_UNAUTHORIZED);

        $traitee = $this->service->reinitialiser($demande, $acteur);

        return response()
            ->json([
                'data' => PasswordResetRequestResource::make($traitee)->resolve($request),
                'meta' => [
                    'mot_de_passe_temporaire' => DemandeReinitialisationMotDePasseService::MOT_DE_PASSE_TEMPORAIRE,
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
