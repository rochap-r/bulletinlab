<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Contracts\NoteEntryServiceInterface;
use App\Domains\Evaluation\Exceptions\EvaluationException;
use App\Models\Note;
use App\Models\ModeleMaximum;
use App\Models\Bulletin;
use Illuminate\Support\Facades\DB;

class NoteEntryService implements NoteEntryServiceInterface
{
    public function saveNote(array $data): Note
    {
        return DB::transaction(function () use ($data) {
            $bulletin = Bulletin::findOrFail($data['bulletin_id']);

            if (!$this->validateMaximum($data['node_id'], $data['periode_id'], $data['valeur'])) {
                throw new EvaluationException('Note exceeds maximum allowed');
            }

            $existingNote = Note::where('bulletin_id', $data['bulletin_id'])
                ->where('node_id', $data['node_id'])
                ->where('periode_id', $data['periode_id'])
                ->first();

            if ($existingNote) {
                $oldValue = $existingNote->valeur;
                $existingNote->update([
                    'valeur' => $data['valeur'],
                    'saisie_par' => $data['saisie_par'] ?? null,
                    'date_saisie' => now(),
                    'commentaire' => $data['commentaire'] ?? null,
                ]);

                if ($oldValue != $data['valeur']) {
                    $existingNote->historique()->create([
                        'ancienne_valeur' => $oldValue,
                        'nouvelle_valeur' => $data['valeur'],
                        'modifie_par' => $data['saisie_par'] ?? null,
                        'motif' => 'Modification de note',
                    ]);
                }

                return $existingNote;
            }

            return Note::create([
                'tenant_id' => $bulletin->tenant_id,
                'bulletin_id' => $data['bulletin_id'],
                'node_id' => $data['node_id'],
                'periode_id' => $data['periode_id'],
                'valeur' => $data['valeur'],
                'saisie_par' => $data['saisie_par'] ?? null,
                'date_saisie' => now(),
                'commentaire' => $data['commentaire'] ?? null,
            ]);
        });
    }

    public function validateMaximum(int $nodeId, int $periodeId, float $note): bool
    {
        $maximum = ModeleMaximum::where('node_id', $nodeId)
            ->where('periode_id', $periodeId)
            ->first();

        if (!$maximum) {
            return true;
        }

        return $note <= $maximum->maxima;
    }
}
