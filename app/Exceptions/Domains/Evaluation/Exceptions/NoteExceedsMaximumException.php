<?php

namespace App\Exceptions\Domains\Evaluation\Exceptions;

use Exception;

class NoteExceedsMaximumException extends Exception
{
    public function __construct(float $note, float $maximum)
    {
        parent::__construct("La note {$note} dépasse le maximum autorisé de {$maximum}.");
    }
}
