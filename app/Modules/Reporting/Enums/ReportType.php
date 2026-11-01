<?php

namespace App\Modules\Reporting\Enums;

enum ReportType: string
{
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Final => 'Informe definitivo',
        };
    }
}
