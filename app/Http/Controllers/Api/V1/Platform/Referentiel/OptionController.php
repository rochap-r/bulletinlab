<?php

namespace App\Http\Controllers\Api\V1\Platform\Referentiel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\Referentiel\OptionRequest;
use App\Http\Resources\Api\V1\Platform\Referentiel\OptionResource;
use App\Models\RefCycle;
use App\Models\RefOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class OptionController extends Controller
{
    public function index(int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        return OptionResource::collection($cycle->options)->response();
    }

    public function store(OptionRequest $request, int $cycle): JsonResponse
    {
        $cycle = RefCycle::query()->findOrFail($cycle);

        $option = $cycle->options()->create($request->validated());

        return (new OptionResource($option))->response()->setStatusCode(201);
    }

    public function update(OptionRequest $request, int $cycle, int $option): JsonResponse
    {
        $optionModel = RefOption::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($option);

        $optionModel->update($request->validated());

        return (new OptionResource($optionModel->refresh()))->response();
    }

    public function destroy(int $cycle, int $option): Response
    {
        $optionModel = RefOption::query()
            ->where('cycle_id', $cycle)
            ->findOrFail($option);

        $optionModel->delete();

        return response()->noContent();
    }
}
