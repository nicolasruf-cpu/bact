<?php

namespace App\Modules\Identification\Enums;

enum IdentificationLevel: string
{
    case Preliminary = 'preliminary';
    case Definitive = 'definitive';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Preliminar',
            self::Definitive => 'Definitiva',
        };
    }
}
