<?php

namespace App\Domains\Evaluation\Services;

use App\Exceptions\Domains\Evaluation\Exceptions\MaximumNotFoundException;
use App\Exceptions\Domains\Evaluation\Exceptions\NoteExceedsMaximumException;
use App\Models\Classe;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;

class NoteValidationService
{
    public function validateMaximum(int $matiereId, int $periodeId, int $classeId, float $note): bool
    {
        $classe = Classe::findOrFail($classeId);
        $maximum = $this->getMaximum($matiereId, $periodeId, $classeId);

        if ($note > $maximum) {
            throw new NoteExceedsMaximumException($note, $maximum);
        }

        return true;
    }

    public function getMaximum(int $matiereId, int $periodeId, int $classeId): float
    {
        $classe = Classe::findOrFail($classeId);

        $node = ModeleNode::where('matiere_id', $matiereId)
            ->whereHas('modeleBulletin', function ($query) use ($classe) {
                $query->where('classe_id', $classe->id);
            })
            ->first();

        if (! $node) {
            throw new MaximumNotFoundException;
        }

        $modele = ModeleMaximum::where('node_id', $node->id)
            ->where('periode_id', $periodeId)
            ->first();

        if (! $modele) {
            throw new MaximumNotFoundException;
        }

        return $modele->maxima;
    }
}
