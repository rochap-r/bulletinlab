<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\AuditLog;
use App\Models\OuvertureAnnuelle;
use App\Models\Utilisateur;

final class CloseEstablishmentSchoolYearService
{
    public function close(
        OuvertureAnnuelle $ouverture,
        Utilisateur $actor,
        ?string $motif = null,
    ): OuvertureAnnuelle {
        if ($ouverture->isCloturee()) {
            return $ouverture;
        }

        $previous = (string) $ouverture->statut;

        $ouverture->update([
            'statut' => OuvertureAnnuelle::STATUT_CLOTUREE,
            'cloturee_at' => now(),
            'cloturee_par' => $actor->getKey(),
        ]);

        AuditLog::query()->create([
            'tenant_id' => $ouverture->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'OUVERTURE_ANNUELLE_CLOTUREE',
            'table_cible' => $ouverture->getTable(),
            'ligne_cible_id' => $ouverture->getKey(),
            'anciennes_valeurs' => ['statut' => $previous],
            'nouvelles_valeurs' => [
                'statut' => OuvertureAnnuelle::STATUT_CLOTUREE,
                'motif' => $motif,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $ouverture;
    }
}
