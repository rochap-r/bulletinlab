<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\SuiviEncodageNote;
use App\Models\VerrouillagePeriode;

class PeriodeLockService
{
    public function lock(int $periodeId, int $classeId, int $userId, ?string $motif = null): VerrouillagePeriode
    {
        $incomplete = SuiviEncodageNote::where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->where('statut', '!=', 'TERMINE')
            ->exists();

        if ($incomplete) {
            throw new \Exception('Certains encodages ne sont pas terminés pour cette période.');
        }

        return VerrouillagePeriode::updateOrCreate(
            [
                'classe_id' => $classeId,
                'periode_id' => $periodeId,
            ],
            [
                'verrouille' => true,
                'verrouille_par' => $userId,
                'date_verrouillage' => now(),
            ]
        );
    }

    public function unlock(int $periodeId, int $classeId): void
    {
        VerrouillagePeriode::where('periode_id', $periodeId)
            ->where('classe_id', $classeId)
            ->update([
                'verrouille' => false,
                'verrouille_par' => null,
                'date_verrouillage' => null,
            ]);
    }

    public function isLocked(int $periodeId, int $classeId): bool
    {
        $lock = VerrouillagePeriode::where('periode_id', $periodeId)
            ->where('classe_id', $classeId)
            ->first();

        return $lock ? $lock->verrouille : false;
    }
}
