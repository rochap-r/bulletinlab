<?php

namespace App\Enums;

enum CalendarCycle: string
{
    case Tous = 'TOUS';
    case Maternelle = 'MATERNELLE';
    case Primaire = 'PRIMAIRE';
    case SecondaireTechnique = 'SECONDAIRE_TECHNIQUE';
}
