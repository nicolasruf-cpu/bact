<?php

namespace App\Modules\Cultures\Enums;

/**
 * Lo que se vio en cada placa en una lectura (revisión de marchas A3).
 */
enum PlateObservation: string
{
    case NoGrowth = 'no_growth';
    case Growth = 'growth';
    case NoNewGrowth = 'no_new_growth';

    public function label(): string
    {
        return match ($this) {
            self::NoGrowth => 'Sin desarrollo',
            self::Growth => 'Desarrollo',
            self::NoNewGrowth => 'Sin desarrollo nuevo',
        };
    }
}
