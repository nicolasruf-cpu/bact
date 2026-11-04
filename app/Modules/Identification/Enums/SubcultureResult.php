<?php

namespace App\Modules\Identification\Enums;

enum SubcultureResult: string
{
    case Pure = 'pure';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Pure => 'Cultivo puro',
            self::Mixed => 'No está puro',
        };
    }
}
