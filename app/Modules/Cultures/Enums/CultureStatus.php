<?php

namespace App\Modules\Cultures\Enums;

/**
 * Estados del cultivo (docs/functional/STATE_MACHINES.md).
 */
enum CultureStatus: string
{
    case Incubating = 'incubating';
    case Workup = 'workup';
    case ClosedNegative = 'closed_negative';
    case ClosedPositive = 'closed_positive';
    case ClosedNotSignificant = 'closed_not_significant';
    case ClosedContaminated = 'closed_contaminated';

    public function label(): string
    {
        return match ($this) {
            self::Incubating => 'En incubación',
            self::Workup => 'En estudio (aislamientos)',
            self::ClosedNegative => 'Cerrado: sin desarrollo',
            self::ClosedPositive => 'Cerrado: positivo',
            self::ClosedNotSignificant => 'Cerrado: no significativo',
            self::ClosedContaminated => 'Cerrado: contaminado',
        };
    }

    public function isClosed(): bool
    {
        return ! in_array($this, [self::Incubating, self::Workup], true);
    }
}
