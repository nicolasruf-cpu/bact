<?php

namespace App\Modules\Reporting\Enums;

enum ReportType: string
{
    case Preliminary = 'preliminary';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Informe preliminar',
            self::Final => 'Informe definitivo',
        };
    }
}
