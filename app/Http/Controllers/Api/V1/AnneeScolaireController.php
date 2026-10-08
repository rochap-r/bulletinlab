<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginationRequest;
use App\Http\Resources\Api\V1\SchoolYearResource;
use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

final class AnneeScolaireController extends Controller
{
    public function index(PaginationRequest $request): JsonResponse
    {
        $schoolYears = AnneeScolaire::query()
            ->latest('id')
            ->paginate($request->integer('per_page', 50));

        return SchoolYearResource::collection($schoolYears)->response();
    }

    public function courante(): JsonResponse
    {
        $annee = AnneeScolaire::forDate();

        if (! $annee) {
            throw (new ModelNotFoundException)->setModel(AnneeScolaire::class);
        }

        return SchoolYearResource::make($annee)->response();
    }

    public function show(int $anneesScolaire): JsonResponse
    {
        return SchoolYearResource::make(
            AnneeScolaire::query()->findOrFail($anneesScolaire),
        )->response();
    }
}
