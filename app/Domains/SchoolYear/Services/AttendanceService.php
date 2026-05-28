<?php

namespace App\Domains\SchoolYear\Services;

use App\Models\PresenceEleve;

class AttendanceService
{
    public function recordAttendance(array $data): PresenceEleve
    {
        return PresenceEleve::updateOrCreate(
            [
                'inscription_id' => $data['inscription_id'],
                'date_presence' => $data['date_presence'],
                'moment' => $data['moment'] ?? 'JOURNEE',
            ],
            [
                'tenant_id' => $data['tenant_id'],
                'etablissement_id' => $data['etablissement_id'],
                'annee_scolaire_id' => $data['annee_scolaire_id'],
                'classe_id' => $data['classe_id'],
                'periode_id' => $data['periode_id'] ?? null,
                'statut' => $data['statut'],
                'observation' => $data['observation'] ?? null,
                'saisi_par' => $data['saisi_par'] ?? null,
            ]
        );
    }

    public function getAttendance(int $classeId, ?string $date = null, ?int $periodeId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PresenceEleve::where('classe_id', $classeId)
            ->with('inscription.eleve');

        if ($date) {
            $query->where('date_presence', $date);
        }

        if ($periodeId) {
            $query->where('periode_id', $periodeId);
        }

        return $query->orderBy('date_presence')->get();
    }

    public function getStudentAttendance(int $inscriptionId, ?string $startDate = null, ?string $endDate = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = PresenceEleve::where('inscription_id', $inscriptionId);

        if ($startDate) {
            $query->where('date_presence', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date_presence', '<=', $endDate);
        }

        return $query->orderBy('date_presence')->get();
    }
}
