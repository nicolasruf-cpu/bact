<?php

namespace App\Modules\Susceptibility\Enums;

enum SusceptibilityTestStatus: string
{
    case SetUp = 'set_up';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SetUp => 'En incubación',
            self::Completed => 'Leído',
            self::Cancelled => 'Descartado',
        };
    }
}
