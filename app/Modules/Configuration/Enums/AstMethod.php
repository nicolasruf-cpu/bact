<?php

namespace App\Modules\Configuration\Enums;

enum AstMethod: string
{
    case Disk = 'disk';
    case Mic = 'mic';

    public function label(): string
    {
        return match ($this) {
            self::Disk => 'Difusión (halo, mm)',
            self::Mic => 'CIM (µg/mL)',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Disk => 'mm',
            self::Mic => 'µg/mL',
        };
    }
}
