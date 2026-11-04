<?php

namespace App\Modules\Workflow\Enums;

use App\Modules\Identity\Enums\Permission;

/**
 * Mesadas genéricas (WORKBENCHES.md): una vista sobre actividades de ciertos tipos.
 * La de siembra tiene pantalla propia (tandas). La de procesamiento junta el examen
 * directo y el repique de los caldos de enriquecimiento.
 */
enum Workbench: string
{
    case Processing = 'processing';
    case Reading = 'reading';
    case Identification = 'identification';
    case Susceptibility = 'susceptibility';
    case Validation = 'validation';

    public function title(): string
    {
        return match ($this) {
            self::Processing => 'Mesada de procesamiento',
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
            self::Processing => [ActivityType::DirectExam, ActivityType::SubcultureEnrichment],
            self::Reading => [ActivityType::ReadCulture],
            self::Identification => [ActivityType::Identify, ActivityType::ReadIdentificationTests, ActivityType::InstrumentResult, ActivityType::ReadIsolateSubculture, ActivityType::ReferralResult],
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
            self::Processing => [Permission::DirectExamsPerform, Permission::CulturesInoculate],
            self::Reading => [Permission::CulturesReadNegative, Permission::CulturesReadPositive],
            self::Identification => [Permission::IdentificationPerform, Permission::IdentificationBiochemRead],
            self::Susceptibility => [Permission::AstSetup, Permission::AstRead],
            self::Validation => [Permission::ReportsValidate],
        };
    }
}
