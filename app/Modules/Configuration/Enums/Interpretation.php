<?php

namespace App\Modules\Configuration\Enums;

enum Interpretation: string
{
    case Susceptible = 'S';
    case Intermediate = 'I';
    case Resistant = 'R';

    public function label(): string
    {
        return match ($this) {
            self::Susceptible => 'Sensible',
            self::Intermediate => 'Intermedio',
            self::Resistant => 'Resistente',
        };
    }
}
