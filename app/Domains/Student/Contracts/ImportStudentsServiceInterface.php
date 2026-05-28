<?php

namespace App\Domains\Student\Contracts;

interface ImportStudentsServiceInterface
{
    public function import(array $data, int $classeId, int $anneeScolaireId): array;
}
