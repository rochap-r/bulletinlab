<?php

namespace App\Exceptions\Domains\Evaluation\Exceptions;

use Exception;

class MaximumNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Maximum non trouvé pour cette matière, période et classe.');
    }
}
