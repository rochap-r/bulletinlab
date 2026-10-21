<?php

namespace App\Domains\SchoolYear\Actions;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Enums\CalendarCycle;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PublishNationalCalendarAction
{
    /**
     * @return array{annee: AnneeScolaire, created: bool}
     */
    public function handle(CalendrierScolaire $calendar): array
    {
        try {
            return Cache::lock('publication-calendrier-national', 15)->block(
                5,
                fn (): array => DB::transaction(function () use ($calendar): array {
                    $lockedCalendar = CalendrierScolaire::query()
                        ->lockForUpdate()
                        ->findOrFail($calendar->getKey());
                    $lockedCalendar->load(['profilsCycles', 'periodes', 'evenements']);
                    $this->assertReady($lockedCalendar);

                    $label = AnneeScolaire::libellePourDate($lockedCalendar->date_debut);
                    $existingYear = AnneeScolaire::query()
                        ->where('libelle', $label)
                        ->lockForUpdate()
                        ->first();

                    if ($existingYear !== null) {
                        if ($existingYear->calendrier_scolaire_id !== $lockedCalendar->getKey()) {
                            throw new SchoolYearException(
                                "L’année scolaire {$label} utilise déjà un autre calendrier national.",
                            );
                        }

                        return [
                            'annee' => $this->loadYear($existingYear),
                            'created' => false,
                        ];
                    }

                    $year = AnneeScolaire::query()->create([
                        'calendrier_scolaire_id' => $lockedCalendar->getKey(),
                        'libelle' => $label,
                        'date_debut' => $lockedCalendar->date_debut,
                        'date_fin' => $lockedCalendar->date_fin,
                        'active' => false,
                        'cloturee' => false,
                    ]);

                    return [
                        'annee' => $this->loadYear($year),
                        'created' => true,
                    ];
                }, attempts: 3),
            );
        } catch (LockTimeoutException) {
            throw new SchoolYearException(
                'Une publication de calendrier national est déjà en cours. Réessayez dans quelques instants.',
            );
        }
    }

    private function assertReady(CalendrierScolaire $calendar): void
    {
        $issues = [];

        if (! $calendar->actif) {
            $issues[] = 'activez le calendrier';
        }

        if (! $calendar->officiel) {
            $issues[] = 'marquez-le comme officiel';
        }

        foreach ($this->requiredCycles() as $cycle) {
            $label = match ($cycle) {
                CalendarCycle::Maternelle => 'maternelle',
                CalendarCycle::Primaire => 'primaire',
                CalendarCycle::SecondaireTechnique => 'secondaire/technique',
                CalendarCycle::Tous => 'tous les cycles',
            };

            if (! $calendar->profilsCycles->contains('cycle_scolaire', $cycle)) {
                $issues[] = "ajoutez le profil {$label}";
            }

            if (! $calendar->periodes->contains(
                fn ($period): bool => in_array($period->cycle_scolaire, [CalendarCycle::Tous, $cycle], true),
            )) {
                $issues[] = "ajoutez une période pour le cycle {$label}";
            }

            if (! $calendar->evenements->contains(
                fn ($event): bool => in_array($event->cycle_scolaire, [CalendarCycle::Tous, $cycle], true),
            )) {
                $issues[] = "ajoutez un événement pour le cycle {$label}";
            }
        }

        if ($issues !== []) {
            throw new SchoolYearException(
                'Le programme national n’est pas prêt à être publié : '.implode(' ; ', $issues).'.',
            );
        }
    }

    /** @return array<int, CalendarCycle> */
    private function requiredCycles(): array
    {
        return [
            CalendarCycle::Maternelle,
            CalendarCycle::Primaire,
            CalendarCycle::SecondaireTechnique,
        ];
    }

    private function loadYear(AnneeScolaire $year): AnneeScolaire
    {
        return $year->load([
            'calendrierScolaire.profilsCycles',
            'calendrierScolaire.periodes',
            'calendrierScolaire.evenements',
        ]);
    }
}
