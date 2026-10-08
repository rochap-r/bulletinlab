<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\AnneeScolaire;
use App\Models\AuditLog;
use App\Models\Utilisateur;

final class CloturerAnneeScolaireService
{
    public function cloturer(
        AnneeScolaire $annee,
        Utilisateur $actor,
        ?string $motif = null,
    ): AnneeScolaire {
        $previous = (bool) $annee->cloturee;

        if ($previous) {
            return $annee;
        }

        $annee->update(['cloturee' => true]);

        AuditLog::query()->create([
            'tenant_id' => null,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'ANNEE_CLOTUREE',
            'table_cible' => $annee->getTable(),
            'ligne_cible_id' => $annee->getKey(),
            'anciennes_valeurs' => ['cloturee' => $previous],
            'nouvelles_valeurs' => [
                'cloturee' => true,
                'motif' => $motif,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $annee;
    }
}
