<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Contracts\OpenSchoolYearServiceInterface;
use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\CalendrierPeriode;
use App\Models\AnneePeriodeMapping;
use App\Models\TenantCycleActif;
use App\Models\RefCycle;
use Illuminate\Support\Facades\DB;

class OpenSchoolYearService implements OpenSchoolYearServiceInterface
{
    public function openSchoolYear(int $tenantId, array $data): AnneeScolaire
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $calendrier = CalendrierScolaire::findOrFail($data['calendrier_id']);

            $anneeScolaire = AnneeScolaire::create([
                'tenant_id' => $tenantId,
                'calendrier_scolaire_id' => $calendrier->id,
                'libelle' => $data['libelle'],
                'date_debut' => $data['date_debut'] ?? $calendrier->date_debut,
                'date_fin' => $data['date_fin'] ?? $calendrier->date_fin,
                'active' => true,
                'cloturee' => false,
            ]);

            $periodes = CalendrierPeriode::where('calendrier_scolaire_id', $calendrier->id)
                ->orderBy('ordre')
                ->get();

            foreach ($periodes as $periode) {
                AnneePeriodeMapping::create([
                    'annee_scolaire_id' => $anneeScolaire->id,
                    'calendrier_periode_id' => $periode->id,
                    'code_periode_template' => $periode->code,
                    'saisie_ouverte' => false,
                    'verrouillee' => false,
                ]);
            }

            if (isset($data['cycles']) && is_array($data['cycles'])) {
                foreach ($data['cycles'] as $cycleCode) {
                    $cycle = RefCycle::where('code', $cycleCode)->first();
                    if ($cycle) {
                        TenantCycleActif::create([
                            'tenant_id' => $tenantId,
                            'annee_scolaire_id' => $anneeScolaire->id,
                            'cycle_id' => $cycle->id,
                            'actif' => true,
                        ]);
                    }
                }
            }

            return $anneeScolaire;
        });
    }
}
