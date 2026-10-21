<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case Rentree = 'RENTREE';
    case Reprise = 'REPRISE';
    case Vacances = 'VACANCES';
    case Conge = 'CONGE';
    case Examen = 'EXAMEN';
    case ExamenNational = 'EXAMEN_NATIONAL';
    case Proclamation = 'PROCLAMATION';
    case Deliberation = 'DELIBERATION';
    case Stage = 'STAGE';
    case JourFerie = 'JOUR_FERIE';
    case SemaineThematique = 'SEMAINE_THEMATIQUE';
    case Administratif = 'ADMINISTRATIF';
    case Reunion = 'REUNION';
}
