<?php

namespace Database\Seeders;

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

        $anneeId = DB::table('annees_scolaires')->updateOrInsert(
            ['libelle' => $libelle, 'calendrier_scolaire_id' => $calendrier->id],
            [
                'date_debut' => $calendrier->date_debut,
                'date_fin' => $calendrier->date_fin,
                'active' => true,
                'cloturee' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $anneeId = DB::table('annees_scolaires')
            ->where('calendrier_scolaire_id', $calendrier->id)
            ->where('libelle', $libelle)
            ->value('id');

        $periodes = DB::table('calendrier_periodes')
            ->where('calendrier_scolaire_id', $calendrier->id)
            ->orderBy('ordre')
            ->get();

        foreach ($periodes as $periode) {
            DB::table('annee_periode_mappings')->updateOrInsert(
                [
                    'annee_scolaire_id' => $anneeId,
                    'calendrier_periode_id' => $periode->id,
                ],
                [
                    'code_periode_template' => $periode->code,
                    'saisie_ouverte' => false,
                    'verrouillee' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
