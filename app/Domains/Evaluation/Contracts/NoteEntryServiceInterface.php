<?php

namespace App\Domains\Evaluation\Contracts;

use App\Models\Note;

interface NoteEntryServiceInterface
{
    public function saveNote(array $data): Note;
    public function validateMaximum(int $nodeId, int $periodeId, float $note): bool;
}
