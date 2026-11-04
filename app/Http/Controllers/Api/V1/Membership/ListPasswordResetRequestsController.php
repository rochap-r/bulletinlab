<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Membership\PasswordResetRequestResource;
use App\Models\DemandeReinitialisationMotDePasse;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListPasswordResetRequestsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var Utilisateur $acteur */
        $acteur = $request->user();
        $administrateur = $acteur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
        abort_unless($administrateur || $acteur->isSchoolPasswordResetManager(), 403);
        abort_unless($administrateur || $acteur->etablissement_id !== null, 403);

        $demandes = DemandeReinitialisationMotDePasse::query()
            ->with(['utilisateur.etablissement'])
            ->where('tenant_id', $acteur->tenant_id)
            ->where('destinataire', DemandeReinitialisationMotDePasse::DESTINATAIRE_ECOLE)
            ->enAttente()
            ->when(! $administrateur, fn ($query) => $query->whereHas(
                'utilisateur',
                fn ($utilisateur) => $utilisateur->where('etablissement_id', $acteur->etablissement_id),
            ))
            ->latest()
            ->get();

        return PasswordResetRequestResource::collection($demandes)
            ->additional(['meta' => ['en_attente' => $demandes->count()]]);
    }
}
