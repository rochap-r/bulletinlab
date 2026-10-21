<?php

namespace App\Domains\SchoolYear\Services;

use App\Enums\CalendarAudience;
use App\Enums\CalendarCycle;
use App\Models\CalendrierEvenement;
use App\Models\CalendrierPeriode;
use App\Models\CalendrierScolaire;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarPilotageService
{
    /**
     * @return array<string, mixed>
     */
    public function calendar(
        CalendrierScolaire $calendar,
        CalendarCycle $cycle,
        CalendarAudience $audience,
        CarbonImmutable $date,
    ): array {
        $calendar->loadMissing(['periodes', 'evenements']);

        $periods = $calendar->periodes
            ->filter(fn (CalendrierPeriode $period): bool => in_array($period->cycle_scolaire, [CalendarCycle::Tous, $cycle], true))
            ->map(fn (CalendrierPeriode $period): array => $this->period($period))
            ->sortBy([
                fn (array $period): string => $period['date_debut'],
                fn (array $period): int => $period['ordre'],
            ])
            ->values();

        $events = $calendar->evenements
            ->filter(fn (CalendrierEvenement $event): bool => in_array($event->cycle_scolaire, [CalendarCycle::Tous, $cycle], true))
            ->filter(fn (CalendrierEvenement $event): bool => in_array(CalendarAudience::Tous->value, $event->audiences, true)
                || in_array($audience->value, $event->audiences, true))
            ->map(fn (CalendrierEvenement $event): array => $this->event($event))
            ->sortBy('date_debut')
            ->values();

        $currentPeriod = $periods
            ->filter(fn (array $period): bool => $date->betweenIncluded($period['date_debut'], $period['date_fin']))
            ->sortBy(fn (array $period): int => $this->periodPriority($period['type_periode']))
            ->first();
        $currentEvents = $events
            ->filter(fn (array $event): bool => $date->betweenIncluded($event['date_debut'], $event['date_fin']))
            ->values();
        $nextEvent = $events
            ->filter(fn (array $event): bool => $date->isBefore($event['date_debut']))
            ->map(function (array $event) use ($date): array {
                $event['jours_avant'] = (int) $date->diffInDays($event['date_debut']);

                return $event;
            })
            ->first();

        return [
            'date_reference' => $date->toDateString(),
            'cycle' => $cycle->value,
            'audience' => $audience->value,
            'boussole' => $this->compass($currentPeriod, $currentEvents, $nextEvent),
            'periode_courante' => $currentPeriod,
            'evenements_en_cours' => $currentEvents,
            'prochain_evenement' => $nextEvent,
            'periodes' => $periods,
            'evenements' => $events,
            'alertes' => $this->alerts($calendar, $cycle, $audience, $date),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(
        CalendrierScolaire $calendar,
        CalendarCycle $cycle,
        CalendarAudience $audience,
        CarbonImmutable $date,
    ): Collection {
        $calendar->loadMissing('evenements');

        return $calendar->evenements
            ->filter(fn ($event): bool => in_array($event->cycle_scolaire, [CalendarCycle::Tous, $cycle], true))
            ->filter(fn ($event): bool => in_array(CalendarAudience::Tous->value, $event->audiences, true)
                || in_array($audience->value, $event->audiences, true))
            ->map(function ($event) use ($date, $cycle, $audience): ?array {
                $start = CarbonImmutable::parse($event->date_debut);
                $end = CarbonImmutable::parse($event->date_fin);

                if ($date->betweenIncluded($start, $end)) {
                    $status = 'EN_COURS';
                    $daysUntil = 0;
                } elseif ($date->isBefore($start)) {
                    $daysUntil = (int) $date->diffInDays($start);
                    if ($daysUntil > $event->delai_alerte_jours) {
                        return null;
                    }
                    $status = 'A_VENIR';
                } else {
                    return null;
                }

                return [
                    'id' => $event->id,
                    'code' => $event->code,
                    'titre' => $event->nom,
                    'description' => $event->description,
                    'type_evenement' => $event->type_evenement->value,
                    'cycle_scolaire' => $event->cycle_scolaire->value,
                    'cycle_demande' => $cycle->value,
                    'audience' => $audience->value,
                    'niveau' => $event->niveau_alerte->value,
                    'statut' => $status,
                    'jours_avant' => $daysUntil,
                    'date_debut' => $start->toDateString(),
                    'date_fin' => $end->toDateString(),
                    'message' => $this->message($event->nom, $status, $daysUntil, $audience),
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $alert): int => match ($alert['niveau']) {
                    'CRITIQUE' => 0,
                    'VIGILANCE' => 1,
                    default => 2,
                },
                fn (array $alert): int => $alert['jours_avant'],
            ])
            ->values();
    }

    private function message(string $name, string $status, int $daysUntil, CalendarAudience $audience): string
    {
        if ($status === 'EN_COURS') {
            return sprintf('%s est en cours.', $name);
        }

        $action = match ($audience) {
            CalendarAudience::Direction => 'Préparez l’organisation de',
            CalendarAudience::Enseignant => 'Anticipez',
            CalendarAudience::Plateforme => 'Surveillez la préparation de',
            CalendarAudience::Tous => 'À préparer :',
        };

        return sprintf('%s %s dans %d jour%s.', $action, $name, $daysUntil, $daysUntil > 1 ? 's' : '');
    }

    /** @return array<string, mixed> */
    private function period(CalendrierPeriode $period): array
    {
        return [
            'id' => $period->id,
            'code' => $period->code,
            'nom' => $period->nom,
            'type_periode' => $period->type_periode,
            'cycle_scolaire' => $period->cycle_scolaire->value,
            'date_debut' => CarbonImmutable::parse($period->date_debut)->toDateString(),
            'date_fin' => CarbonImmutable::parse($period->date_fin)->toDateString(),
            'ordre' => $period->ordre,
            'saisie_notes_ouverte' => (bool) $period->saisie_notes_ouverte,
        ];
    }

    /** @return array<string, mixed> */
    private function event(CalendrierEvenement $event): array
    {
        return [
            'id' => $event->id,
            'code' => $event->code,
            'titre' => $event->nom,
            'description' => $event->description,
            'type_evenement' => $event->type_evenement->value,
            'cycle_scolaire' => $event->cycle_scolaire->value,
            'niveau' => $event->niveau_alerte->value,
            'date_debut' => CarbonImmutable::parse($event->date_debut)->toDateString(),
            'date_fin' => CarbonImmutable::parse($event->date_fin)->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $currentPeriod
     * @param  Collection<int, array<string, mixed>>  $currentEvents
     * @param  array<string, mixed>|null  $nextEvent
     * @return array<string, string|null>
     */
    private function compass(?array $currentPeriod, Collection $currentEvents, ?array $nextEvent): array
    {
        $currentEvent = $currentEvents->first();

        if ($currentEvent !== null) {
            $suffix = $currentPeriod !== null ? sprintf(' Nous sommes aussi dans %s.', $currentPeriod['nom']) : '';

            return [
                'statut' => 'EVENEMENT',
                'titre' => $currentEvent['titre'],
                'message' => sprintf('%s est en cours.%s', $currentEvent['titre'], $suffix),
            ];
        }

        if ($currentPeriod !== null) {
            return [
                'statut' => 'PERIODE',
                'titre' => $currentPeriod['nom'],
                'message' => sprintf('Nous sommes dans %s.', $currentPeriod['nom']),
            ];
        }

        if ($nextEvent !== null) {
            return [
                'statut' => 'INTER_PERIODE',
                'titre' => 'Entre deux étapes du calendrier',
                'message' => sprintf('Prochain repère : %s dans %d jour%s.', $nextEvent['titre'], $nextEvent['jours_avant'], $nextEvent['jours_avant'] > 1 ? 's' : ''),
            ];
        }

        return [
            'statut' => 'SANS_REPERE',
            'titre' => 'Aucun repère actif',
            'message' => 'Aucune période ni aucun événement à venir ne sont configurés pour ce cycle.',
        ];
    }

    private function periodPriority(string $type): int
    {
        return match ($type) {
            'EXAMEN' => 0,
            'PERIODE' => 1,
            'TRIMESTRE' => 2,
            'SEMESTRE' => 3,
            default => 4,
        };
    }
}
