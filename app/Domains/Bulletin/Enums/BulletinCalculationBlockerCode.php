<?php

namespace App\Domains\Bulletin\Enums;

enum BulletinCalculationBlockerCode: string
{
    case PeriodInactive = 'PERIOD_INACTIVE';
    case PeriodNotCalculable = 'PERIOD_NOT_CALCULABLE';
    case NoSlots = 'NO_SLOTS';
    case DuplicateSlot = 'DUPLICATE_SLOT';
    case UnsupportedNodeType = 'UNSUPPORTED_NODE_TYPE';
    case InvalidMaximum = 'INVALID_MAXIMUM';
    case UnsupportedCoefficient = 'UNSUPPORTED_COEFFICIENT';
    case MissingResult = 'MISSING_RESULT';
    case UnsupportedResultStatus = 'UNSUPPORTED_RESULT_STATUS';
    case InvalidValue = 'INVALID_VALUE';
}
