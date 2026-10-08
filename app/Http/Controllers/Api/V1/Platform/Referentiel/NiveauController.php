<?php

namespace App\Http\Controllers\Api\V1\Platform\Referentiel;

use App\Domains\Referentiel\Exceptions\ReferentielConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\Referentiel\NiveauRequest;
use App\Http\Resources\Api\V1\Platform\Referentiel\NiveauResource;
use App\Models\Classe;
use App\Models\RefCycle;
use App\Models\RefNiveau;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class NiveauController extends Controller
{
    public function index(int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        return NiveauResource::collection($cycle->niveaux)->response();
    }

    public function store(NiveauRequest $request, int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        $niveau = $cycle->niveaux()->create($request->validated());

        return (new NiveauResource($niveau))->response()->setStatusCode(201);
    }

    public function update(NiveauRequest $request, int $cycle, int $niveau): JsonResponse
    {
        $niveauModel = RefNiveau::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($niveau);

        $niveauModel->update($request->validated());

        return (new NiveauResource($niveauModel->refresh()))->response();
    }

    public function destroy(int $cycle, int $niveau): Response
    {
        $niveauModel = RefNiveau::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($niveau);

        if (Classe::query()->where('niveau_id', $niveau)->exists()) {
            throw new ReferentielConflictException(
                'Ce niveau est référencé par des classes et ne peut pas être supprimé.',
            );
        }

        $niveauModel->delete();

        return response()->noContent();
    }
}
