<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\CalendarPilotageService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\CalendarAudience;
use App\Enums\CalendarCycle;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SchoolCalendarAlertController extends Controller
{
    public function __construct(
        private CalendarPilotageService $pilotageService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle' => ['nullable', Rule::enum(CalendarCycle::class)->except(CalendarCycle::Tous)],
        ]);
        $school = $this->establishmentContext->current();
        $cycle = $this->resolveCycle($school->type_etablissement, $validated['cycle'] ?? null);
        $user = $request->user();
        abort_unless($user instanceof Utilisateur, 403);
        $audience = $user->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT', 'GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES', 'SECRETARIAT'])
            ? CalendarAudience::Direction
            : CalendarAudience::Enseignant;
        $date = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
        $year = AnneeScolaire::forDate($date)?->load('calendrierScolaire.evenements');

        if ($year?->calendrierScolaire === null) {
            return response()->json(['data' => [], 'meta' => [
                'date_reference' => $date->toDateString(),
                'cycle' => $cycle->value,
                'audience' => $audience->value,
                'calendrier_id' => null,
            ]]);
        }

        return response()->json([
            'data' => $this->pilotageService->alerts($year->calendrierScolaire, $cycle, $audience, $date),
            'meta' => [
                'date_reference' => $date->toDateString(),
                'cycle' => $cycle->value,
                'audience' => $audience->value,
                'calendrier_id' => $year->calendrierScolaire->id,
            ],
        ]);
    }

    private function resolveCycle(string $schoolType, ?string $requestedCycle): CalendarCycle
    {
        if ($schoolType === 'MIXTE') {
            if ($requestedCycle === null) {
                throw ValidationException::withMessages([
                    'cycle' => ['Choisissez le cycle à piloter pour cet établissement mixte.'],
                ]);
            }

            return CalendarCycle::from($requestedCycle);
        }

        return match ($schoolType) {
            'MATERNELLE' => CalendarCycle::Maternelle,
            'PRIMAIRE' => CalendarCycle::Primaire,
            'SECONDAIRE' => CalendarCycle::SecondaireTechnique,
            default => throw ValidationException::withMessages(['cycle' => ['Le type d’établissement ne correspond à aucun cycle de calendrier.']]),
        };
    }
}
