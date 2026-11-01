<?php

namespace App\Modules\Samples\Enums;

/**
 * Estados de la muestra (docs/functional/STATE_MACHINES.md).
 */
enum SampleStatus: string
{
    case Registered = 'registered';
    case Received = 'received';
    case InProcess = 'in_process';
    case PartiallyReported = 'partially_reported';
    case Reported = 'reported';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrada',
            self::Received => 'Recibida',
            self::InProcess => 'En proceso',
            self::PartiallyReported => 'Informada parcialmente',
            self::Reported => 'Informada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Anulada',
        };
    }
}
