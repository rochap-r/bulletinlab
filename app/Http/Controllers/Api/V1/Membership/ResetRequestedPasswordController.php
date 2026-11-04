<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Auth\Services\DemandeReinitialisationMotDePasseService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Membership\PasswordResetRequestResource;
use App\Models\DemandeReinitialisationMotDePasse;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResetRequestedPasswordController extends Controller
{
    public function __construct(private readonly DemandeReinitialisationMotDePasseService $service) {}

    public function __invoke(Request $request, DemandeReinitialisationMotDePasse $demande): JsonResponse
    {
        /** @var Utilisateur $acteur */
        $acteur = $request->user();
        $administrateur = $acteur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
        abort_unless($administrateur || $acteur->isSchoolPasswordResetManager(), 403);
        abort_unless($demande->destinataire === DemandeReinitialisationMotDePasse::DESTINATAIRE_ECOLE
            && (int) $demande->tenant_id === (int) $acteur->tenant_id, 404);
        abort_unless($administrateur || ($acteur->etablissement_id !== null
            && (int) $demande->utilisateur->etablissement_id === (int) $acteur->etablissement_id), 404);

        $traitee = $this->service->reinitialiserPourEcole($demande, $acteur);

        return response()->json([
            'data' => PasswordResetRequestResource::make($traitee)->resolve($request),
            'meta' => ['mot_de_passe_temporaire' => DemandeReinitialisationMotDePasseService::MOT_DE_PASSE_TEMPORAIRE],
        ])->header('Cache-Control', 'no-store, private');
    }
}
