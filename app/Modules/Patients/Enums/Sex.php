<?php

namespace App\Modules\Patients\Enums;

enum Sex: string
{
    case Female = 'F';
    case Male = 'M';
    case Other = 'X';
    case Unknown = 'U';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::Other => 'Otro / no binario',
            self::Unknown => 'Sin dato',
        };
    }
}
