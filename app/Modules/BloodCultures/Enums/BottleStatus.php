<?php

namespace App\Modules\BloodCultures\Enums;

/**
 * Estados del frasco: en incubación → positivo (sigue el estudio del positivo) o negativo (cerrado).
 */
enum BottleStatus: string
{
    case Incubating = 'incubating';
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Incubating => 'En incubación',
            self::Positive => 'Positivo',
            self::Negative => 'Negativo',
        };
    }
}
