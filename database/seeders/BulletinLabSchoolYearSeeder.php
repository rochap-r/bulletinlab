<?php

namespace Database\Seeders;

use App\Models\AnneePeriodeMapping;
use App\Models\AnneeScolaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabSchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        $calendrier = DB::table('calendriers_scolaires')
            ->where('code', 'RDC_2026_2027')
            ->firstOrFail();

        $libelle = $calendrier->date_debut
            ? AnneeScolaire::libellePourDate($calendrier->date_debut)
            : '2026-2027';

        $anneeScolaire = AnneeScolaire::query()->updateOrCreate(
            [
                'libelle' => $libelle,
                'calendrier_scolaire_id' => $calendrier->id,
            ],
            [
                'date_debut' => $calendrier->date_debut,
                'date_fin' => $calendrier->date_fin,
            ]
        );

        if ($anneeScolaire->wasRecentlyCreated) {
            $anneeScolaire->update([
                'active' => true,
                'cloturee' => false,
            ]);
        }

        $periodes = DB::table('calendrier_periodes')
            ->where('calendrier_scolaire_id', $calendrier->id)
            ->orderBy('ordre')
            ->get();

        foreach ($periodes as $periode) {
            AnneePeriodeMapping::query()->updateOrCreate(
                [
                    'annee_scolaire_id' => $anneeScolaire->id,
                    'calendrier_periode_id' => $periode->id,
                ],
                [
                    'code_periode_template' => $periode->code,
                ]
            );
        }
    }
}
