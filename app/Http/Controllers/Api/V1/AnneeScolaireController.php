<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\SchoolYear\Contracts\OpenSchoolYearServiceInterface;
use App\Models\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnneeScolaireController extends Controller
{
    public function __construct(
        private OpenSchoolYearServiceInterface $schoolYearService
    ) {}

    public function index(): JsonResponse
    {
        $annees = AnneeScolaire::with('tenant', 'etablissement')->get();
        return response()->json($annees);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'calendrier_id' => 'required|integer|exists:calendriers_scolaires,id',
            'libelle' => 'required|string',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'cycles' => 'nullable|array',
        ]);

        $annee = $this->schoolYearService->openSchoolYear($request->tenant_id, $request->all());

        return response()->json($annee, 201);
    }

    public function show(int $id): JsonResponse
    {
        $annee = AnneeScolaire::with('tenant', 'etablissement', 'calendrierScolaire')->findOrFail($id);
        return response()->json($annee);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $annee = AnneeScolaire::findOrFail($id);
        $annee->update($request->all());
        return response()->json($annee);
    }

    public function destroy(int $id): JsonResponse
    {
        $annee = AnneeScolaire::findOrFail($id);
        $annee->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function open(int $id): JsonResponse
    {
        $annee = AnneeScolaire::findOrFail($id);
        $annee->update(['active' => true]);
        return response()->json($annee);
    }
}
