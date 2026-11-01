<?php

namespace App\Modules\Configuration\Enums;

/**
 * Qué hace el sistema con cada opción de lectura (la redacción la define el protocolo).
 */
enum ReadingOutcome: string
{
    case NoGrowth = 'no_growth';
    case Growth = 'growth';
    case MixedFlora = 'mixed_flora';
    case NotSignificant = 'not_significant';
    case Reincubate = 'reincubate';
}
