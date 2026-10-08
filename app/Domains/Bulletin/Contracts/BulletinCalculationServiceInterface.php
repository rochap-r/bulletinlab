<?php

namespace App\Domains\Bulletin\Contracts;

use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationResult;

interface BulletinCalculationServiceInterface
{
    public function calculate(
        BulletinCalculationInput $input,
    ): BulletinCalculationResult;

    public function version(): string;
}
