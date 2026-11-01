<?php

namespace App\Modules\Cultures\Enums;

/**
 * Estados del aislamiento (docs/functional/STATE_MACHINES.md, simplificado para el MVP).
 */
enum IsolateStatus: string
{
    case InIdentification = 'in_identification';
    case Identified = 'identified';
    case Resolved = 'resolved';
    case NotSignificant = 'not_significant';

    public function label(): string
    {
        return match ($this) {
            self::InIdentification => 'En identificación',
            self::Identified => 'Identificado (antibiograma pendiente)',
            self::Resolved => 'Resuelto',
            self::NotSignificant => 'No significativo',
        };
    }

    public function isResolved(): bool
    {
        return $this === self::Resolved || $this === self::NotSignificant;
    }
}
