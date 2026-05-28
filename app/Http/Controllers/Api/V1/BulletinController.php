<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Bulletins\BulletinCalculationService;
use App\Models\Bulletin;
use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BulletinController extends Controller
{
    public function __construct(
        private BulletinCalculationService $calculationService
    ) {}

    public function index(): JsonResponse
    {
        $bulletins = Bulletin::with('tenant', 'eleve', 'classe', 'anneeScolaire')->get();
        return response()->json($bulletins);
    }

    public function show(int $id): JsonResponse
    {
        $bulletin = Bulletin::with('tenant', 'eleve', 'classe', 'anneeScolaire', 'modeleBulletin')->findOrFail($id);
        return response()->json($bulletin);
    }

    public function recalculate(int $id): JsonResponse
    {
        $bulletin = Bulletin::findOrFail($id);
        $recalculated = $this->calculationService->recalculate($bulletin);
        return response()->json($recalculated);
    }

    public function byClasse(int $classeId): JsonResponse
    {
        $classe = Classe::findOrFail($classeId);
        $bulletins = Bulletin::where('classe_id', $classeId)
            ->with('eleve', 'modeleBulletin')
            ->get();
        return response()->json($bulletins);
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'periode_id' => 'required|integer',
        ]);

        $result = $this->calculationService->calculateForClasse($request->classe_id, $request->periode_id);

        return response()->json($result);
    }
}
