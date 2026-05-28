<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\PeriodeLockService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeController extends Controller
{
    public function lock(Request $request, int $periodeId): JsonResponse
    {
        $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'motif' => 'nullable|string',
        ]);

        $classeId = $request->classe_id;
        $userId = auth()->id();
        $motif = $request->motif;

        $lock = app(PeriodeLockService::class)->lock($periodeId, $classeId, $userId, $motif);

        return response()->json($lock, 201);
    }

    public function unlock(Request $request, int $periodeId): JsonResponse
    {
        $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
        ]);

        $classeId = $request->classe_id;

        app(PeriodeLockService::class)->unlock($periodeId, $classeId);

        return response()->json(['message' => 'Période déverrouillée avec succès']);
    }
}
