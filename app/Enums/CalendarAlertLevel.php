<?php

namespace App\Enums;

enum CalendarAlertLevel: string
{
    case Information = 'INFORMATION';
    case Vigilance = 'VIGILANCE';
    case Critique = 'CRITIQUE';
}
