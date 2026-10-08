<?php

namespace App\Domains\Bulletin\Contracts;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModelePeriode;
use App\Models\Proclamation;
use App\Models\Utilisateur;

interface ProclamationServiceInterface
{
    public function generate(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
    ): Proclamation;

    public function findPublishedForClass(
        Etablissement $establishment,
        Classe $classe,
    ): ?Proclamation;
}
