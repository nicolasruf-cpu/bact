<?php

namespace App\Modules\Workflow\Enums;

use App\Modules\Identity\Enums\Permission;

/**
 * Mesadas genéricas (WORKBENCHES.md): una vista sobre actividades de ciertos tipos.
 * La de siembra tiene pantalla propia (tandas).
 */
enum Workbench: string
{
    case Reading = 'reading';
    case Identification = 'identification';
    case Susceptibility = 'susceptibility';
    case Validation = 'validation';

    public function title(): string
    {
        return match ($this) {
            self::Reading => 'Mesada de lectura',
            self::Identification => 'Mesada de identificación',
            self::Susceptibility => 'Mesada de antibiogramas',
            self::Validation => 'Bandeja de validación',
        };
    }

    /**
     * @return list<ActivityType>
     */
    public function activityTypes(): array
    {
        return match ($this) {
            self::Reading => [ActivityType::ReadCulture],
            self::Identification => [ActivityType::Identify],
            self::Susceptibility => [ActivityType::AstSetup, ActivityType::AstRead],
            self::Validation => [ActivityType::ValidateReport],
        };
    }

    /**
     * Alcanza con uno de estos permisos para ver la mesada.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Reading => [Permission::CulturesReadNegative, Permission::CulturesReadPositive],
            self::Identification => [Permission::IdentificationPerform],
            self::Susceptibility => [Permission::AstSetup, Permission::AstRead],
            self::Validation => [Permission::ReportsValidate],
        };
    }
}
