<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Models\AnneeScolaire;
use App\Models\AuditLog;
use App\Models\Etablissement;
use App\Models\OuvertureAnnuelle;
use App\Models\RefCycle;
use App\Models\TenantCycleActif;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

final class OpenEstablishmentSchoolYearService
{
    public function open(
        Etablissement $etablissement,
        Utilisateur $actor,
        ?int $anneeScolaireId = null,
        array $cycles = [],
    ): OuvertureAnnuelle {
        return DB::transaction(function () use ($etablissement, $actor, $anneeScolaireId, $cycles): OuvertureAnnuelle {
            $annee = $anneeScolaireId !== null
                ? AnneeScolaire::query()->findOrFail($anneeScolaireId)
                : AnneeScolaire::forDate();

            if (! $annee || ! $annee->active || $annee->cloturee) {
                throw new SchoolYearException(
                    'Aucune année scolaire globale ouverte.',
                );
            }

            $isReopening = OuvertureAnnuelle::query()
                ->where('etablissement_id', $etablissement->id)
                ->where('annee_scolaire_id', $annee->id)
                ->exists();

            $ouverture = OuvertureAnnuelle::updateOrCreate(
                [
                    'etablissement_id' => $etablissement->id,
                    'annee_scolaire_id' => $annee->id,
                ],
                [
                    'tenant_id' => $etablissement->tenant_id,
                    'statut' => OuvertureAnnuelle::STATUT_OUVERTE,
                    'activee_at' => now(),
                    'activee_par' => $actor->getKey(),
                    'cloturee_at' => null,
                    'cloturee_par' => null,
                ],
            );

            if ($isReopening && $ouverture->isCloturee()) {
                AuditLog::query()->create([
                    'tenant_id' => $etablissement->tenant_id,
                    'utilisateur_id' => $actor->getKey(),
                    'action' => 'OUVERTURE_ANNUELLE_REOUVERTE',
                    'table_cible' => $ouverture->getTable(),
                    'ligne_cible_id' => $ouverture->getKey(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } elseif (! $isReopening) {
                AuditLog::query()->create([
                    'tenant_id' => $etablissement->tenant_id,
                    'utilisateur_id' => $actor->getKey(),
                    'action' => 'OUVERTURE_ANNUELLE_OUVERTE',
                    'table_cible' => $ouverture->getTable(),
                    'ligne_cible_id' => $ouverture->getKey(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            if (is_array($cycles) && count($cycles) > 0) {
                foreach ($cycles as $cycleCode) {
                    $cycle = RefCycle::where('code', $cycleCode)->first();
                    if ($cycle) {
                        TenantCycleActif::updateOrCreate(
                            [
                                'tenant_id' => $etablissement->tenant_id,
                                'annee_scolaire_id' => $annee->id,
                                'cycle_id' => $cycle->id,
                            ],
                            ['actif' => true],
                        );
                    }
                }
            }

            return $ouverture;
        });
    }
}
