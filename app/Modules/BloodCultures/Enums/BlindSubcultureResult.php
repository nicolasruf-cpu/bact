<?php

namespace App\Modules\BloodCultures\Enums;

enum BlindSubcultureResult: string
{
    case NoGrowth = 'no_growth';
    case Growth = 'growth';

    public function label(): string
    {
        return match ($this) {
            self::NoGrowth => 'Sin desarrollo',
            self::Growth => 'Desarrollo',
        };
    }
}
