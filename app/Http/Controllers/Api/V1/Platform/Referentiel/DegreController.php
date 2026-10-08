<?php

namespace App\Http\Controllers\Api\V1\Platform\Referentiel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\Referentiel\DegreRequest;
use App\Http\Resources\Api\V1\Platform\Referentiel\DegreResource;
use App\Models\RefCycle;
use App\Models\RefDegre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class DegreController extends Controller
{
    public function index(int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        return DegreResource::collection($cycle->degres)->response();
    }

    public function store(DegreRequest $request, int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        $degre = $cycle->degres()->create($request->validated());

        return (new DegreResource($degre))->response()->setStatusCode(201);
    }

    public function update(DegreRequest $request, int $cycle, int $degre): JsonResponse
    {
        $degreModel = RefDegre::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($degre);

        $degreModel->update($request->validated());

        return (new DegreResource($degreModel->refresh()))->response();
    }

    public function destroy(int $cycle, int $degre): Response
    {
        $degreModel = RefDegre::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($degre);

        $degreModel->delete();

        return response()->noContent();
    }
}
