<?php

namespace App\Modules\Cultures\Enums;

enum PlateStatus: string
{
    case Incubating = 'incubating';
    case Finished = 'finished';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Incubating => 'En incubación',
            self::Finished => 'Finalizada',
            self::Lost => 'Perdida',
        };
    }
}
