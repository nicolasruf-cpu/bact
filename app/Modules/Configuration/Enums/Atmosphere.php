<?php

namespace App\Modules\Configuration\Enums;

enum Atmosphere: string
{
    case Aerobic = 'aerobic';
    case Co2 = 'co2';
    case Anaerobic = 'anaerobic';
    case Microaerophilic = 'microaerophilic';

    public function label(): string
    {
        return match ($this) {
            self::Aerobic => 'Aerobiosis',
            self::Co2 => 'CO₂',
            self::Anaerobic => 'Anaerobiosis',
            self::Microaerophilic => 'Microaerofilia',
        };
    }
}
