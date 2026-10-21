<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\SchoolYear\Services\CalendarPilotageService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\CalendarAudience;
use App\Enums\CalendarCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SchoolCalendarIndexRequest;
use App\Models\AnneeScolaire;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SchoolCalendarController extends Controller
{
    public function __construct(
        private CalendarPilotageService $pilotageService,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function __invoke(SchoolCalendarIndexRequest $request): JsonResponse
    {
        $school = $this->establishmentContext->current();
        $cycle = $this->resolveCycle($school->type_etablissement, $request->validated('cycle'));
        $user = $request->user();
        abort_unless($user instanceof Utilisateur, 403);
        $audience = $user->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT', 'GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES', 'SECRETARIAT'])
            ? CalendarAudience::Direction
            : CalendarAudience::Enseignant;
        $date = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
        $year = AnneeScolaire::forDate($date)?->load('calendrierScolaire');

        if ($year?->calendrierScolaire === null) {
            return response()->json([
                'data' => [
                    'date_reference' => $date->toDateString(),
                    'cycle' => $cycle->value,
                    'audience' => $audience->value,
                    'annee' => null,
                    'boussole' => [
                        'statut' => 'SANS_CALENDRIER',
                        'titre' => 'Calendrier à configurer',
                        'message' => 'Aucun calendrier scolaire actif n’est rattaché à cette année.',
                    ],
                    'periode_courante' => null,
                    'evenements_en_cours' => [],
                    'prochain_evenement' => null,
                    'periodes' => [],
                    'evenements' => [],
                    'alertes' => [],
                ],
            ]);
        }

        $calendar = $this->pilotageService->calendar($year->calendrierScolaire, $cycle, $audience, $date);
        $calendar['annee'] = [
            'id' => $year->id,
            'libelle' => $year->libelle,
            'date_debut' => $year->date_debut,
            'date_fin' => $year->date_fin,
        ];

        return response()->json(['data' => $calendar]);
    }

    private function resolveCycle(string $schoolType, ?string $requestedCycle): CalendarCycle
    {
        if ($schoolType === 'MIXTE') {
            if ($requestedCycle === null) {
                throw ValidationException::withMessages([
                    'cycle' => ['Choisissez le cycle à afficher pour cet établissement mixte.'],
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
