<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\RefCycle;
use App\Models\SystemTemplateBulletin;
use Illuminate\Http\JsonResponse;

final class ReferentielController extends Controller
{
    /**
     * Catalogue nécessaire aux écrans de création (classes, inscriptions) :
     * années scolaires, cycles avec leurs niveaux et modèles de bulletin
     * actifs. Données globales en lecture seule.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'annees_scolaires' => AnneeScolaire::query()
                ->latest('id')
                ->get(['id', 'libelle', 'active', 'cloturee']),
            'cycles' => RefCycle::query()
                ->where('actif', true)
                ->orderBy('ordre')
                ->with([
                    'niveaux' => fn ($query) => $query
                        ->orderBy('ordre')
                        ->select(['id', 'cycle_id', 'code', 'nom', 'numero']),
                    'options' => fn ($query) => $query
                        ->orderBy('nom')
                        ->select(['id', 'cycle_id', 'code', 'nom']),
                ])
                ->get(['id', 'code', 'nom']),
            'modeles' => SystemTemplateBulletin::query()
                ->where('actif', true)
                ->orderBy('nom')
                ->get(['id', 'code', 'nom']),
        ]]);
    }
}
