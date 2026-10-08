<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\PlatformAnneeScolaireRequest;
use App\Http\Resources\Api\V1\Platform\PlatformSchoolYearResource;
use App\Models\AnneeScolaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnneeScolaireController extends Controller
{
    public function index(): JsonResponse
    {
        $annees = AnneeScolaire::query()
            ->with('calendrierScolaire.periodes')
            ->orderByDesc('date_debut')
            ->get();

        return PlatformSchoolYearResource::collection($annees)->response();
    }

    public function store(PlatformAnneeScolaireRequest $request): JsonResponse
    {
        $annee = AnneeScolaire::query()->create([
            ...$request->validated(),
            'active' => false,
            'cloturee' => false,
        ]);

        return PlatformSchoolYearResource::make($annee->load('calendrierScolaire.periodes'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PlatformAnneeScolaireRequest $request, int $annee): JsonResponse
    {
        $anneeModel = AnneeScolaire::query()->findOrFail($annee);
        $anneeModel->update($request->validated());

        return PlatformSchoolYearResource::make($anneeModel->refresh()->load('calendrierScolaire.periodes'))
            ->response();
    }

    public function activate(Request $request, int $annee): JsonResponse
    {
        $anneeModel = AnneeScolaire::query()->findOrFail($annee);

        if ($anneeModel->cloturee) {
            throw new SchoolYearException(
                'Impossible d\'activer une année scolaire déjà clôturée.',
            );
        }

        AnneeScolaire::query()
            ->whereKeyNot($annee)
            ->update(['active' => false]);

        $anneeModel->update(['active' => true]);

        return PlatformSchoolYearResource::make($anneeModel->refresh()->load('calendrierScolaire.periodes'))
            ->response();
    }

    public function close(Request $request, int $annee): JsonResponse
    {
        $anneeModel = AnneeScolaire::query()->findOrFail($annee);

        if (! $anneeModel->cloturee) {
            $anneeModel->update([
                'cloturee' => true,
                'active' => false,
            ]);
        }

        return PlatformSchoolYearResource::make($anneeModel->refresh()->load('calendrierScolaire.periodes'))
            ->response();
    }
}
