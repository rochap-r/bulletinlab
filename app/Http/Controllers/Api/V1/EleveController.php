<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Student\Contracts\ImportStudentsServiceInterface;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EleveController extends Controller
{
    public function __construct(
        private ImportStudentsServiceInterface $importService
    ) {}

    public function index(): JsonResponse
    {
        $eleves = Eleve::with('tenant', 'etablissement')->get();
        return response()->json($eleves);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'etablissement_id' => 'required|integer|exists:etablissements,id',
            'nom' => 'required|string',
            'postnom' => 'nullable|string',
            'prenom' => 'nullable|string',
            'sexe' => 'nullable|string',
            'date_naissance' => 'nullable|date',
        ]);

        $eleve = Eleve::create($request->all());
        return response()->json($eleve, 201);
    }

    public function show(int $id): JsonResponse
    {
        $eleve = Eleve::with('tenant', 'etablissement', 'inscriptions.classe')->findOrFail($id);
        return response()->json($eleve);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $eleve->update($request->all());
        return response()->json($eleve);
    }

    public function destroy(int $id): JsonResponse
    {
        $eleve = Eleve::findOrFail($id);
        $eleve->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'classe_id' => 'required|integer|exists:classes,id',
            'annee_scolaire_id' => 'required|integer|exists:annees_scolaires,id',
        ]);

        $result = $this->importService->import($request->data, $request->classe_id, $request->annee_scolaire_id);

        return response()->json($result);
    }
}
