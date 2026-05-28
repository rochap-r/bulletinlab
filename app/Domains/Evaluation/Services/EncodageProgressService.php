<?php

namespace App\Domains\Evaluation\Services;

use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\SuiviEncodageNote;
use Illuminate\Support\Collection;

class EncodageProgressService
{
    public function updateProgress(int $classeId, int $nodeId, int $periodeId): void
    {
        $classe = Classe::findOrFail($classeId);

        $totalEleves = Inscription::where('classe_id', $classeId)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->count();

        $notesEncodees = Note::where('node_id', $nodeId)
            ->where('periode_id', $periodeId)
            ->distinct('eleve_id')
            ->count();

        $pourcentage = $totalEleves > 0 ? ($notesEncodees / $totalEleves) * 100 : 0;

        $statut = match (true) {
            $pourcentage == 100 => 'TERMINE',
            $pourcentage > 0 => 'EN_COURS',
            default => 'NON_COMMENCE',
        };

        SuiviEncodageNote::updateOrCreate(
            [
                'classe_id' => $classeId,
                'node_id' => $nodeId,
                'periode_id' => $periodeId,
            ],
            [
                'modele_bulletin_id' => $classe->modeleBulletins->first()?->id,
                'nombre_eleves' => $totalEleves,
                'nombre_notes_saisies' => $notesEncodees,
                'pourcentage_avancement' => $pourcentage,
                'statut' => $statut,
                'derniere_saisie_at' => $notesEncodees > 0 ? now() : null,
            ]
        );
    }

    public function getProgress(int $classeId, int $periodeId): Collection
    {
        return SuiviEncodageNote::where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->with('node.matiere', 'enseignant')
            ->get();
    }
}
