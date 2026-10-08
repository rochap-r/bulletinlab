<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Models\CalendrierScolaire;
use Illuminate\Support\Arr;
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
                $calendar->fill(Arr::except($data, ['periodes']))->save();
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
            }

            return $calendar->load('periodes')->loadCount('annees')->loadExists('mappings');
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
                if ((string) $original->getAttribute($field) !== (string) (is_bool($value) ? (int) $value : $value)) {
                    throw new SchoolYearException('Les périodes d’un calendrier utilisé sont protégées pour conserver les mappings des années scolaires.');
                }
            }
        }
    }
}
