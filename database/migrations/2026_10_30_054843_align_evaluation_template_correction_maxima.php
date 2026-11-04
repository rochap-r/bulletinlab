<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('evaluations')
            ->where('composante_modele', 'EXERCICE_DEVOIR')
            ->whereNotNull('maxima_bulletin')
            ->orderBy('id')
            ->chunkById(100, function ($quarters): void {
                foreach ($quarters as $quarter) {
                    DB::transaction(function () use ($quarter): void {
                        $quarterCorrectionMaximum = $this->correctionMaximum(
                            (float) $quarter->maxima_bulletin,
                        );
                        $evaluations = DB::table('evaluations')
                            ->where('tenant_id', $quarter->tenant_id)
                            ->where('classe_id', $quarter->classe_id)
                            ->where('node_id', $quarter->node_id)
                            ->where('periode_id', $quarter->periode_id)
                            ->whereIn('composante_modele', [
                                'EXERCICE_DEVOIR',
                                'CAHIER',
                                'INTERROGATION',
                            ])
                            ->lockForUpdate()
                            ->get(['id', 'composante_modele', 'maxima']);

                        foreach ($evaluations as $evaluation) {
                            $newMaximum = $evaluation->composante_modele === 'INTERROGATION'
                                ? $quarterCorrectionMaximum * 2
                                : $quarterCorrectionMaximum;
                            $oldMaximum = (float) $evaluation->maxima;

                            if ($oldMaximum <= 0 || abs($oldMaximum - $newMaximum) < 0.001) {
                                continue;
                            }

                            DB::table('resultats_evaluations')
                                ->where('evaluation_id', $evaluation->id)
                                ->whereNotNull('valeur')
                                ->orderBy('id')
                                ->chunkById(500, function ($results) use ($oldMaximum, $newMaximum): void {
                                    foreach ($results as $result) {
                                        DB::table('resultats_evaluations')
                                            ->where('id', $result->id)
                                            ->update([
                                                'valeur' => round((float) $result->valeur * $newMaximum / $oldMaximum, 2),
                                                'lock_version' => (int) $result->lock_version + 1,
                                                'updated_at' => now(),
                                            ]);
                                    }
                                });

                            DB::table('evaluations')
                                ->where('id', $evaluation->id)
                                ->update([
                                    'maxima' => $newMaximum,
                                    'lock_version' => DB::raw('lock_version + 1'),
                                    'updated_at' => now(),
                                ]);
                        }
                    });
                }
            });
    }

    public function down(): void {}

    private function correctionMaximum(float $bulletinMaximum): float
    {
        if ($bulletinMaximum <= 5) {
            return 5;
        }

        if ($bulletinMaximum <= 10) {
            return 10;
        }

        return ceil($bulletinMaximum / 10) * 10;
    }
};
