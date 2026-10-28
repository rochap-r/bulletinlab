<?php

namespace App\Domains\Responsable\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class ResponsableAuthenticationException extends Exception implements ShouldntReport {}
