<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClasseStudentsResource;
use App\Http\Resources\Api\V1\EncodageProgressResource;
use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClasseController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = Classe::with('tenant', 'etablissement', 'anneeScolaire', 'cycle', 'niveau', 'option')->get();
        return response()->json($classes);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'etablissement_id' => 'required|integer|exists:etablissements,id',
            'annee_scolaire_id' => 'required|integer|exists:annees_scolaires,id',
            'cycle_id' => 'required|integer|exists:ref_cycles,id',
            'niveau_id' => 'required|integer|exists:ref_niveaux,id',
            'nom' => 'required|string',
            'parallele' => 'nullable|string',
            'code' => 'nullable|string',
        ]);

        $classe = Classe::create($request->all());
        return response()->json($classe, 201);
    }

    public function show(int $id): JsonResponse
    {
        $classe = Classe::with('tenant', 'etablissement', 'anneeScolaire', 'cycle', 'niveau', 'option', 'inscriptions.eleve')->findOrFail($id);
        return response()->json($classe);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $classe = Classe::findOrFail($id);
        $classe->update($request->all());
        return response()->json($classe);
    }

    public function destroy(int $id): JsonResponse
    {
        $classe = Classe::findOrFail($id);
        $classe->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function cloneTemplate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'system_template_id' => 'required|integer|exists:system_templates_bulletin,id',
        ]);

        $classe = Classe::findOrFail($id);
        $classe->update(['system_template_id' => $request->system_template_id]);

        return response()->json($classe);
    }

    public function students(Request $request, int $id): ClasseStudentsResource
    {
        $periodeId = $request->query('periode_id');
        $matiereId = $request->query('matiere_id');

        $classe = Classe::with([
            'inscriptions.eleve',
            'inscriptions.eleve.notes' => function ($query) use ($periodeId, $matiereId) {
                $query->where('periode_id', $periodeId)
                      ->where('matiere_id', $matiereId);
            }
        ])->findOrFail($id);

        return ClasseStudentsResource::make($classe);
    }

    public function encodingProgress(Request $request, int $id)
    {
        $periodeId = $request->query('periode_id');

        $progress = app(\App\Domains\Evaluation\Services\EncodageProgressService::class)->getProgress($id, $periodeId);

        return EncodageProgressResource::collection($progress);
    }
}
