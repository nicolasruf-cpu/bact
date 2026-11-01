<?php

namespace App\Modules\BloodCultures\Enums;

enum BottleMethod: string
{
    case Automated = 'automated';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automated => 'Equipo automatizado',
            self::Manual => 'Manual (repiques)',
        };
    }
}
