<?php

namespace App\Domains\Evaluation\Services;

use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\SuiviEncodageNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EncodageProgressService
{
    public function updateProgress(int $classeId, int $nodeId, int $periodeId): void
    {
        DB::transaction(function () use ($classeId, $nodeId, $periodeId): void {
            $classe = Classe::query()
                ->lockForUpdate()
                ->findOrFail($classeId);

            $totalEleves = Inscription::query()
                ->where('classe_id', $classeId)
                ->where('annee_scolaire_id', $classe->annee_scolaire_id)
                ->where('statut', 'ACTIF')
                ->count();

            $notesEncodees = Note::query()
                ->whereHas(
                    'bulletin',
                    fn ($query) => $query
                        ->where('classe_id', $classeId)
                        ->whereHas(
                            'inscription',
                            fn ($query) => $query->where('statut', 'ACTIF'),
                        ),
                )
                ->where('node_id', $nodeId)
                ->where('periode_id', $periodeId)
                ->recordedResults()
                ->distinct()
                ->count('bulletin_id');

            $pourcentage = $totalEleves > 0 ? ($notesEncodees / $totalEleves) * 100 : 0;

            $statut = match (true) {
                $pourcentage == 100 => 'TERMINE',
                $pourcentage > 0 => 'EN_COURS',
                default => 'NON_COMMENCE',
            };

            $modeleBulletinId = $classe->modeleBulletins()
                ->where('annee_scolaire_id', $classe->annee_scolaire_id)
                ->value('id');

            if ($modeleBulletinId === null) {
                return;
            }

            SuiviEncodageNote::query()->updateOrCreate(
                [
                    'classe_id' => $classeId,
                    'node_id' => $nodeId,
                    'periode_id' => $periodeId,
                ],
                [
                    'tenant_id' => $classe->tenant_id,
                    'modele_bulletin_id' => $modeleBulletinId,
                    'nombre_eleves' => $totalEleves,
                    'nombre_notes_saisies' => $notesEncodees,
                    'pourcentage_avancement' => $pourcentage,
                    'statut' => $statut,
                    'derniere_saisie_at' => $notesEncodees > 0 ? now() : null,
                ],
            );
        }, attempts: 3);
    }

    public function getProgress(int $classeId, int $periodeId): Collection
    {
        return SuiviEncodageNote::where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->with('node.matiere', 'enseignant')
            ->get();
    }
}
