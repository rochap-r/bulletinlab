<?php

namespace App\Enums;

enum StatutLienResponsable: string
{
    case EnAttente = 'EN_ATTENTE';
    case Verifie = 'VERIFIE';
    case Revoque = 'REVOQUE';
}
