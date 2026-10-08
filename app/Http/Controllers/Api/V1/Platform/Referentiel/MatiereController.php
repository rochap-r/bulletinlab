<?php

namespace App\Http\Controllers\Api\V1\Platform\Referentiel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\Referentiel\MatiereRequest;
use App\Http\Resources\Api\V1\Platform\Referentiel\MatiereResource;
use App\Models\RefMatiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MatiereController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matieres = RefMatiere::query()
            ->when($request->boolean('actifs_seulement'), fn ($query) => $query->where('actif', true))
            ->orderBy('nom')
            ->get();

        return MatiereResource::collection($matieres)->response();
    }

    public function store(MatiereRequest $request): JsonResponse
    {
        $matiere = RefMatiere::query()->create($request->validated());

        return MatiereResource::make($matiere)->response()->setStatusCode(201);
    }

    public function update(MatiereRequest $request, int $matiere): JsonResponse
    {
        $matiereModel = RefMatiere::query()->findOrFail($matiere);
        $matiereModel->update($request->validated());

        return MatiereResource::make($matiereModel->refresh())->response();
    }

    public function destroy(int $matiere): Response
    {
        RefMatiere::query()->findOrFail($matiere)->delete();

        return response()->noContent();
    }
}
