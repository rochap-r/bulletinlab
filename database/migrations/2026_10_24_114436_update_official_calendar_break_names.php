<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $calendarId = DB::table('calendriers_scolaires')
            ->where('code', 'RDC_2026_2027')
            ->value('id');

        if ($calendarId === null) {
            return;
        }

        foreach ([
            'CONGE_T1' => ['Congé de détente du 1er trimestre', 'Congé de détente de novembre'],
            'VACANCES_T1' => ['Vacances du 1er trimestre', 'Vacances de fin d’année'],
            'CONGE_T2' => ['Congé de détente du 2ème trimestre', 'Congé de détente de février'],
            'VACANCES_T2' => ['Vacances du 2ème trimestre', 'Vacances de mars et avril'],
        ] as $code => [$previousName, $newName]) {
            DB::table('calendrier_evenements')
                ->where('calendrier_scolaire_id', $calendarId)
                ->where('code', $code)
                ->where('nom', $previousName)
                ->update(['nom' => $newName]);
        }
    }

    public function down(): void
    {
        $calendarId = DB::table('calendriers_scolaires')
            ->where('code', 'RDC_2026_2027')
            ->value('id');

        if ($calendarId === null) {
            return;
        }

        foreach ([
            'CONGE_T1' => ['Congé de détente de novembre', 'Congé de détente du 1er trimestre'],
            'VACANCES_T1' => ['Vacances de fin d’année', 'Vacances du 1er trimestre'],
            'CONGE_T2' => ['Congé de détente de février', 'Congé de détente du 2ème trimestre'],
            'VACANCES_T2' => ['Vacances de mars et avril', 'Vacances du 2ème trimestre'],
        ] as $code => [$currentName, $previousName]) {
            DB::table('calendrier_evenements')
                ->where('calendrier_scolaire_id', $calendarId)
                ->where('code', $code)
                ->where('nom', $currentName)
                ->update(['nom' => $previousName]);
        }
    }
};
