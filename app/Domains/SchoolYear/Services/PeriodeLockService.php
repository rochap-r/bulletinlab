<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\ClassArchiveConflictException;
use App\Domains\SchoolYear\Exceptions\EncodingIncompleteException;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\ModelePeriode;
use App\Models\SuiviEncodageNote;
use App\Models\Utilisateur;
use App\Models\VerrouillagePeriode;
use Illuminate\Support\Facades\DB;

final class PeriodeLockService
{
    public function lock(
        Classe $classe,
        ModelePeriode $periode,
        Utilisateur $actor,
        string $motif,
    ): VerrouillagePeriode {
        return DB::transaction(function () use ($classe, $periode, $actor, $motif): VerrouillagePeriode {
            $lockedClass = Classe::query()
                ->lockForUpdate()
                ->findOrFail($classe->id);
            $this->assertClassIsActive($lockedClass);
            $periodState = VerrouillagePeriode::query()
                ->where('classe_id', $lockedClass->id)
                ->where('periode_id', $periode->id)
                ->lockForUpdate()
                ->first();

            if ($periodState?->verrouille) {
                return $periodState;
            }

            $progressRows = SuiviEncodageNote::query()
                ->where('classe_id', $lockedClass->id)
                ->where('periode_id', $periode->id)
                ->lockForUpdate()
                ->get();

            if ($progressRows->isEmpty()
                || $progressRows->contains(
                    fn (SuiviEncodageNote $progress): bool => $progress->statut !== 'TERMINE',
                )) {
                throw new EncodingIncompleteException(
                    'All note entry slots must be complete before locking this period.',
                );
            }

            if ($periodState) {
                $periodState->update([
                    'verrouille' => true,
                    'verrouille_par' => $actor->id,
                    'date_verrouillage' => now(),
                ]);
            } else {
                $periodState = VerrouillagePeriode::query()->create([
                    'tenant_id' => $lockedClass->tenant_id,
                    'classe_id' => $lockedClass->id,
                    'periode_id' => $periode->id,
                    'verrouille' => true,
                    'verrouille_par' => $actor->id,
                    'date_verrouillage' => now(),
                ]);
            }

            SuiviEncodageNote::query()
                ->whereKey($progressRows->modelKeys())
                ->update(['statut' => 'VERROUILLE']);

            $this->recordAudit(
                action: 'PERIODE_VERROUILLEE',
                periodState: $periodState,
                actor: $actor,
                oldLockedState: false,
                newLockedState: true,
                motif: $motif,
            );

            return $periodState->refresh();
        }, attempts: 3);
    }

    public function reopen(
        Classe $classe,
        ModelePeriode $periode,
        Utilisateur $actor,
        string $motif,
    ): ?VerrouillagePeriode {
        return DB::transaction(function () use ($classe, $periode, $actor, $motif): ?VerrouillagePeriode {
            $lockedClass = Classe::query()
                ->lockForUpdate()
                ->findOrFail($classe->id);
            $this->assertClassIsActive($lockedClass);
            $periodState = VerrouillagePeriode::query()
                ->where('classe_id', $lockedClass->id)
                ->where('periode_id', $periode->id)
                ->lockForUpdate()
                ->first();

            if (! $periodState?->verrouille) {
                return $periodState;
            }

            $progressRows = SuiviEncodageNote::query()
                ->where('classe_id', $lockedClass->id)
                ->where('periode_id', $periode->id)
                ->lockForUpdate()
                ->get();

            $periodState->update([
                'verrouille' => false,
                'verrouille_par' => null,
                'date_verrouillage' => null,
            ]);

            SuiviEncodageNote::query()
                ->whereKey($progressRows->modelKeys())
                ->where('statut', 'VERROUILLE')
                ->update(['statut' => 'TERMINE']);

            $this->recordAudit(
                action: 'PERIODE_REOUVERTE',
                periodState: $periodState,
                actor: $actor,
                oldLockedState: true,
                newLockedState: false,
                motif: $motif,
            );

            return $periodState->refresh();
        }, attempts: 3);
    }

    public function isLocked(int $periodeId, int $classeId): bool
    {
        return VerrouillagePeriode::query()
            ->where('periode_id', $periodeId)
            ->where('classe_id', $classeId)
            ->where('verrouille', true)
            ->exists();
    }

    private function assertClassIsActive(Classe $class): void
    {
        if (! $class->active) {
            throw new ClassArchiveConflictException(
                'Archived classes do not allow period transitions.',
            );
        }
    }

    private function recordAudit(
        string $action,
        VerrouillagePeriode $periodState,
        Utilisateur $actor,
        bool $oldLockedState,
        bool $newLockedState,
        string $motif,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $periodState->tenant_id,
            'utilisateur_id' => $actor->id,
            'action' => $action,
            'table_cible' => $periodState->getTable(),
            'ligne_cible_id' => $periodState->id,
            'anciennes_valeurs' => ['verrouille' => $oldLockedState],
            'nouvelles_valeurs' => [
                'verrouille' => $newLockedState,
                'motif' => $motif,
            ],
        ]);
    }
}
