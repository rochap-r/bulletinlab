<?php

namespace App\Enums;

enum TypeLienResponsable: string
{
    case Pere = 'PERE';
    case Mere = 'MERE';
    case Tuteur = 'TUTEUR';
    case Autre = 'AUTRE';
}
