<?php

namespace App\Modules\Reporting\Enums;

enum ReportStatus: string
{
    case Issued = 'issued';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitido',
            self::Superseded => 'Reemplazado',
        };
    }
}
