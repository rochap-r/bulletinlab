<?php

namespace App\Services\Bulletins;

use App\Models\Classe;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\SuiviEncodageNote;

class EncodingProgressService
{
    public function refresh(Classe $classe, ModeleNode $node, ModelePeriode $periode): SuiviEncodageNote
    {
        $nombreEleves = $classe->inscriptions()->where('statut', 'ACTIF')->count();

        $nombreNotes = Note::query()
            ->recordedResults()
            ->where('node_id', $node->id)
            ->where('periode_id', $periode->id)
            ->whereHas(
                'bulletin',
                fn ($reportCard) => $reportCard
                    ->where('classe_id', $classe->id)
                    ->whereHas(
                        'inscription',
                        fn ($enrollment) => $enrollment
                            ->where('statut', 'ACTIF'),
                    ),
            )
            ->distinct()
            ->count('bulletin_id');

        $pourcentage = $nombreEleves > 0 ? round(($nombreNotes / $nombreEleves) * 100, 2) : 0;

        $statut = match (true) {
            $nombreNotes === 0 => 'NON_COMMENCE',
            $nombreNotes >= $nombreEleves && $nombreEleves > 0 => 'TERMINE',
            default => 'EN_COURS',
        };

        return SuiviEncodageNote::updateOrCreate(
            ['classe_id' => $classe->id, 'node_id' => $node->id, 'periode_id' => $periode->id],
            [
                'tenant_id' => $classe->tenant_id,
                'modele_bulletin_id' => $node->modele_bulletin_id,
                'nombre_eleves' => $nombreEleves,
                'nombre_notes_saisies' => $nombreNotes,
                'pourcentage_avancement' => $pourcentage,
                'statut' => $statut,
                'derniere_saisie_at' => now(),
            ]
        );
    }
}
