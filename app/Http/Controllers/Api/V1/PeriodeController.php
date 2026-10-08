<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\PeriodeLockService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Evaluation\PeriodTransitionRequest;
use App\Models\Classe;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

final class PeriodeController extends Controller
{
    public function __construct(
        private PeriodeLockService $periodLockService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function lock(
        PeriodTransitionRequest $request,
        int $classe,
        int $periode,
    ): JsonResponse {
        $resolvedClass = $this->resolveClass($classe);
        $resolvedPeriod = $this->resolvePeriod($resolvedClass, $periode);
        $periodState = $this->periodLockService->lock(
            $resolvedClass,
            $resolvedPeriod,
            $this->authenticatedUser($request),
            (string) $request->validated('motif'),
        );

        return response()->json([
            'data' => [
                'classe_id' => $resolvedClass->id,
                'periode_id' => $resolvedPeriod->id,
                'verrouille' => $periodState->verrouille,
                'date_verrouillage' => $periodState->date_verrouillage,
            ],
        ]);
    }

    public function reopen(
        PeriodTransitionRequest $request,
        int $classe,
        int $periode,
    ): JsonResponse {
        $resolvedClass = $this->resolveClass($classe);
        $resolvedPeriod = $this->resolvePeriod($resolvedClass, $periode);
        $this->periodLockService->reopen(
            $resolvedClass,
            $resolvedPeriod,
            $this->authenticatedUser($request),
            (string) $request->validated('motif'),
        );

        return response()->json([
            'data' => [
                'classe_id' => $resolvedClass->id,
                'periode_id' => $resolvedPeriod->id,
                'verrouille' => false,
                'date_verrouillage' => null,
            ],
        ]);
    }

    private function resolveClass(int $classId): Classe
    {
        return Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($classId);
    }

    private function resolvePeriod(Classe $classe, int $periodId): ModelePeriode
    {
        return ModelePeriode::query()
            ->whereHas(
                'modeleBulletin',
                fn ($query) => $query->where('classe_id', $classe->id),
            )
            ->findOrFail($periodId);
    }

    private function authenticatedUser(PeriodTransitionRequest $request): Utilisateur
    {
        $user = $request->user();

        if (! $user instanceof Utilisateur) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
