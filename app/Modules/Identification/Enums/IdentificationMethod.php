<?php

namespace App\Modules\Identification\Enums;

enum IdentificationMethod: string
{
    case Biochemical = 'biochemical';
    case Vitek = 'vitek';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Biochemical => 'Pruebas bioquímicas',
            self::Vitek => 'VITEK 2 (carga manual)',
            self::Other => 'Otro método',
        };
    }
}
