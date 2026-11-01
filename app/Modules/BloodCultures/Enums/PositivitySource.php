<?php

namespace App\Modules\BloodCultures\Enums;

/**
 * Cómo se detectó la positividad del frasco.
 */
enum PositivitySource: string
{
    case Instrument = 'instrument';
    case BlindSubculture = 'blind_subculture';
    case Visual = 'visual';

    public function label(): string
    {
        return match ($this) {
            self::Instrument => 'Alarma del equipo',
            self::BlindSubculture => 'Desarrollo en el repique',
            self::Visual => 'Signos visibles en el frasco',
        };
    }
}
