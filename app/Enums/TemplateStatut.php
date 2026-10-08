<?php

namespace App\Enums;

enum TemplateStatut: string
{
    case BROUILLON = 'BROUILLON';
    case VALIDE = 'VALIDE';
    case PUBLIE = 'PUBLIE';
    case DEPRECIE = 'DEPRECIE';
}
