<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabCalendarSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('calendriers_scolaires')->updateOrInsert(
            ['code' => 'RDC_2026_2027'],
            [
                'libelle' => 'Calendrier scolaire RDC 2026-2027',
                'pays' => 'RDC',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-07-31',
                'officiel' => true,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $calendarId = DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');

        $periodes = [
            ['P1', '1ère période', 'PERIODE', '2026-09-01', '2026-10-31', 1],
            ['P2', '2ème période', 'PERIODE', '2026-11-01', '2026-12-20', 2],
            ['EXAM_S1', 'Examen premier semestre', 'EXAMEN', '2027-01-10', '2027-01-25', 3],
            ['P3', '3ème période', 'PERIODE', '2027-02-01', '2027-03-31', 4],
            ['P4', '4ème période', 'PERIODE', '2027-04-01', '2027-05-31', 5],
            ['EXAM_S2', 'Examen deuxième semestre', 'EXAMEN', '2027-06-10', '2027-06-30', 6],
            ['DELIBERATION', 'Délibération', 'DELIBERATION', '2027-07-01', '2027-07-15', 7],
            ['PROCLAMATION', 'Proclamation', 'PROCLAMATION', '2027-07-16', '2027-07-31', 8],
        ];

        foreach ($periodes as [$code,$nom,$type,$debut,$fin,$ordre]) {
            DB::table('calendrier_periodes')->updateOrInsert(
                ['calendrier_scolaire_id' => $calendarId, 'code' => $code],
                [
                    'nom' => $nom,
                    'type_periode' => $type,
                    'date_debut' => $debut,
                    'date_fin' => $fin,
                    'ordre' => $ordre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
