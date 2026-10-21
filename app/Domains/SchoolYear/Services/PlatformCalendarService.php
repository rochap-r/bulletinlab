<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Models\CalendrierScolaire;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlatformCalendarService
{
    /** @param array<string, mixed> $data */
    public function save(?int $calendarId, array $data): CalendrierScolaire
    {
        return DB::transaction(function () use ($calendarId, $data): CalendrierScolaire {
            $calendar = $calendarId === null ? new CalendrierScolaire : CalendrierScolaire::query()->lockForUpdate()->findOrFail($calendarId);
            $periods = $data['periodes'];
            if ($calendar->exists && $calendar->isUsed()) {
                $this->assertStructureUnchanged($calendar, $data);
                $calendar->update(Arr::only($data, ['libelle', 'pays', 'officiel', 'actif']));
            } else {
                $calendar->fill(Arr::except($data, ['profils_cycles', 'periodes', 'evenements']))->save();
                $this->syncProfiles($calendar, $data['profils_cycles']);
                $ids = collect($periods)->pluck('id')->filter()->all();
                $calendar->periodes()->whereNotIn('id', $ids)->delete();
                foreach ($ids as $periodId) {
                    $calendar->periodes()->whereKey($periodId)->update(['code' => 'EDIT_'.Str::uuid()]);
                }
                foreach ($periods as $period) {
                    $attributes = Arr::except($period, ['id']);
                    if (! empty($period['id'])) {
                        $calendar->periodes()->findOrFail($period['id'])->update($attributes);
                    } else {
                        $calendar->periodes()->create($attributes);
                    }
                }
                $this->syncEvents($calendar, $data['evenements']);
            }

            return $calendar->load(['profilsCycles', 'periodes', 'evenements'])->loadCount('annees')->loadExists('mappings');
        }, attempts: 3);
    }

    public function delete(int $calendarId): void
    {
        DB::transaction(function () use ($calendarId): void {
            $calendar = CalendrierScolaire::query()->lockForUpdate()->findOrFail($calendarId);
            if ($calendar->isUsed()) {
                throw new SchoolYearException('Ce calendrier est utilisé par une année scolaire ou un mapping de période. Désactivez-le plutôt que de le supprimer.');
            }
            $calendar->delete();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    private function assertStructureUnchanged(CalendrierScolaire $calendar, array $data): void
    {
        foreach (['code', 'date_debut', 'date_fin'] as $field) {
            if ((string) $calendar->getAttribute($field) !== (string) $data[$field]) {
                throw new SchoolYearException('La structure d’un calendrier utilisé ne peut plus être modifiée. Son libellé, son pays et son statut restent modifiables.');
            }
        }
        $existing = $calendar->periodes()->get();
        if ($existing->count() !== count($data['periodes'])) {
            throw new SchoolYearException('Les périodes d’un calendrier utilisé ne peuvent pas être ajoutées ou supprimées.');
        }
        foreach ($data['periodes'] as $period) {
            $original = $existing->firstWhere('id', $period['id'] ?? null);
            if ($original === null) {
                throw new SchoolYearException('Les périodes d’un calendrier utilisé doivent conserver leurs identifiants.');
            }
            foreach (Arr::except($period, ['id']) as $field => $value) {
                $originalValue = $original->getAttribute($field);
                if ($originalValue instanceof BackedEnum) {
                    $originalValue = $originalValue->value;
                }
                if ($originalValue instanceof DateTimeInterface) {
                    $originalValue = $originalValue->format('Y-m-d');
                }
                if (is_bool($originalValue)) {
                    $originalValue = (int) $originalValue;
                }
                if ((string) $originalValue !== (string) (is_bool($value) ? (int) $value : $value)) {
                    throw new SchoolYearException('Les périodes d’un calendrier utilisé sont protégées pour conserver les mappings des années scolaires.');
                }
            }
        }

        $this->assertRelatedStructureUnchanged($calendar->profilsCycles()->get(), $data['profils_cycles']);
        $this->assertRelatedStructureUnchanged($calendar->evenements()->get(), $data['evenements']);
    }

    /** @param array<int, array<string, mixed>> $profiles */
    private function syncProfiles(CalendrierScolaire $calendar, array $profiles): void
    {
        $ids = collect($profiles)->pluck('id')->filter()->all();
        $calendar->profilsCycles()->whereNotIn('id', $ids)->delete();

        foreach ($profiles as $profile) {
            $attributes = Arr::except($profile, ['id']);
            if (! empty($profile['id'])) {
                $calendar->profilsCycles()->findOrFail($profile['id'])->update($attributes);
            } else {
                $calendar->profilsCycles()->create($attributes);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $events */
    private function syncEvents(CalendrierScolaire $calendar, array $events): void
    {
        $ids = collect($events)->pluck('id')->filter()->all();
        $calendar->evenements()->whereNotIn('id', $ids)->delete();
        foreach ($ids as $eventId) {
            $calendar->evenements()->whereKey($eventId)->update(['code' => 'EDIT_'.Str::uuid()]);
        }

        foreach ($events as $event) {
            $attributes = Arr::except($event, ['id']);
            if (! empty($event['id'])) {
                $calendar->evenements()->findOrFail($event['id'])->update($attributes);
            } else {
                $calendar->evenements()->create($attributes);
            }
        }
    }

    /**
     * @param  Collection<int, Model>  $existing
     * @param  array<int, array<string, mixed>>  $submitted
     */
    private function assertRelatedStructureUnchanged($existing, array $submitted): void
    {
        if ($existing->count() !== count($submitted)) {
            throw new SchoolYearException('Les profils et événements d’un calendrier utilisé ne peuvent pas être ajoutés ou supprimés.');
        }

        foreach ($submitted as $attributes) {
            $original = $existing->firstWhere('id', $attributes['id'] ?? null);
            if ($original === null) {
                throw new SchoolYearException('Les profils et événements utilisés doivent conserver leurs identifiants.');
            }

            foreach (Arr::except($attributes, ['id']) as $field => $value) {
                $originalValue = $original->getAttribute($field);
                if ($originalValue instanceof BackedEnum) {
                    $originalValue = $originalValue->value;
                }
                if ($originalValue instanceof DateTimeInterface) {
                    $originalValue = $originalValue->format('Y-m-d');
                }
                if (is_array($value)) {
                    $originalValue = is_string($originalValue) ? json_decode($originalValue, true) : $originalValue;
                    if ($originalValue !== $value) {
                        throw new SchoolYearException('La structure de pilotage d’un calendrier utilisé est protégée.');
                    }
                } elseif ((string) $originalValue !== (string) (is_bool($value) ? (int) $value : $value)) {
                    throw new SchoolYearException('La structure de pilotage d’un calendrier utilisé est protégée.');
                }
            }
        }
    }
}
