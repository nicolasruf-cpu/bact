<?php

namespace App\Modules\Identification\Enums;

enum IdentificationLevel: string
{
    case Preliminary = 'preliminary';
    case Definitive = 'definitive';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Preliminar',
            self::Definitive => 'Definitiva',
            self::Inconclusive => 'No concluyente',
        };
    }
}
