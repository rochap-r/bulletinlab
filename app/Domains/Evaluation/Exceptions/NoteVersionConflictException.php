<?php

namespace App\Domains\Evaluation\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class NoteVersionConflictException extends RuntimeException implements ShouldntReport {}
