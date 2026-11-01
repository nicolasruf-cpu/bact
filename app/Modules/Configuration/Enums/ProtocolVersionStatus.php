<?php

namespace App\Modules\Configuration\Enums;

/**
 * Ciclo de vida de una versión de configuración clínica (MICROBIOLOGY_RULES §1).
 */
enum ProtocolVersionStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Approved => 'Aprobada',
            self::Active => 'Vigente',
            self::Retired => 'Retirada',
        };
    }
}
