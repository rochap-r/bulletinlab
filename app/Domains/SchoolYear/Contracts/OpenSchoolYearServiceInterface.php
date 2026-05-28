<?php

namespace App\Domains\SchoolYear\Contracts;

use App\Models\AnneeScolaire;

interface OpenSchoolYearServiceInterface
{
    public function openSchoolYear(int $tenantId, array $data): AnneeScolaire;
}
