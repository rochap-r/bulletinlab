<?php

namespace App\Http\Controllers\Api\V1\Platform\Referentiel;

use App\Domains\Referentiel\Exceptions\ReferentielConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\Referentiel\CycleRequest;
use App\Http\Resources\Api\V1\Platform\Referentiel\CycleResource;
use App\Models\Classe;
use App\Models\RefCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CycleController extends Controller
{
    public function index(): JsonResponse
    {
        $cycles = RefCycle::query()
            ->with(['degres', 'niveaux', 'options'])
            ->orderBy('ordre')
            ->get();

        return CycleResource::collection($cycles)->response();
    }

    public function store(CycleRequest $request): JsonResponse
    {
        $cycle = RefCycle::query()->create($request->validated());

        return CycleResource::make($cycle->load(['degres', 'niveaux', 'options']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CycleRequest $request, int $cycle): JsonResponse
    {
        $cycleModel = RefCycle::query()->findOrFail($cycle);
        $cycleModel->update($request->validated());

        return CycleResource::make($cycleModel->refresh()->load(['degres', 'niveaux', 'options']))
            ->response();
    }

    public function destroy(Request $request, int $cycle): Response
    {
        $cycleModel = RefCycle::query()->findOrFail($cycle);

        if (Classe::query()->where('cycle_id', $cycle)->exists()) {
            throw new ReferentielConflictException(
                'Ce cycle est référencé par des classes et ne peut pas être supprimé.',
            );
        }

        $cycleModel->delete();

        return response()->noContent();
    }
}
