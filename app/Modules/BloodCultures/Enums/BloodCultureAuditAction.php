<?php

namespace App\Modules\BloodCultures\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum BloodCultureAuditAction: string implements AuditAction
{
    case BottleLoaded = 'blood_culture.loaded';
    case BottlePositive = 'blood_culture.positive';
    case BottleNegative = 'blood_culture.negative';
    case BottleSubcultured = 'blood_culture.subcultured';
    case BlindSubcultureDone = 'blood_culture.blind_subculture';
    case BlindSubcultureRead = 'blood_culture.blind_subculture_read';
    case GramRecorded = 'blood_culture.gram';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::BottleLoaded => 'Cargó un frasco de hemocultivo',
            self::BottlePositive => 'Registró un frasco positivo',
            self::BottleNegative => 'Cerró un frasco como negativo',
            self::BottleSubcultured => 'Subcultivó un frasco positivo',
            self::BlindSubcultureDone => 'Repicó un frasco',
            self::BlindSubcultureRead => 'Leyó un repique',
            self::GramRecorded => 'Registró el Gram de un frasco',
        };
    }
}
