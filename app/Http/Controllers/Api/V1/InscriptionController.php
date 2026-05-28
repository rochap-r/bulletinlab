<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $inscriptions = Inscription::with('tenant', 'eleve', 'classe', 'anneeScolaire')->get();
        return response()->json($inscriptions);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'eleve_id' => 'required|integer|exists:eleves,id',
            'classe_id' => 'required|integer|exists:classes,id',
            'annee_scolaire_id' => 'required|integer|exists:annees_scolaires,id',
            'statut' => 'nullable|string',
        ]);

        $inscription = Inscription::create($request->all());
        return response()->json($inscription, 201);
    }

    public function show(int $id): JsonResponse
    {
        $inscription = Inscription::with('tenant', 'eleve', 'classe', 'anneeScolaire')->findOrFail($id);
        return response()->json($inscription);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $inscription = Inscription::findOrFail($id);
        $inscription->update($request->all());
        return response()->json($inscription);
    }

    public function destroy(int $id): JsonResponse
    {
        $inscription = Inscription::findOrFail($id);
        $inscription->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
