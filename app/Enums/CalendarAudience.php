<?php

namespace App\Enums;

enum CalendarAudience: string
{
    case Tous = 'TOUS';
    case Direction = 'DIRECTION';
    case Enseignant = 'ENSEIGNANT';
    case Plateforme = 'PLATEFORME';
}
