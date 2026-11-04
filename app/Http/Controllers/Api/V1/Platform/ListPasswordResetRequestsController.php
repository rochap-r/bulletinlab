<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Platform\PasswordResetRequestResource;
use App\Models\DemandeReinitialisationMotDePasse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListPasswordResetRequestsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $enAttente = DemandeReinitialisationMotDePasse::query()
            ->with(['utilisateur', 'tenant', 'traiteePar'])
            ->enAttente()
            ->where('destinataire', DemandeReinitialisationMotDePasse::DESTINATAIRE_PLATEFORME)
            ->latest()
            ->get();

        $traitees = DemandeReinitialisationMotDePasse::query()
            ->with(['utilisateur', 'tenant', 'traiteePar'])
            ->where('statut', DemandeReinitialisationMotDePasse::STATUT_TRAITEE)
            ->where('destinataire', DemandeReinitialisationMotDePasse::DESTINATAIRE_PLATEFORME)
            ->latest('traitee_at')
            ->limit(50)
            ->get();

        return PasswordResetRequestResource::collection($enAttente->concat($traitees))
            ->additional([
                'meta' => ['en_attente' => $enAttente->count()],
            ]);
    }
}
