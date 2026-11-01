<?php

namespace App\Modules\Workflow\Enums;

/**
 * Tipos de actividad (docs/functional/ACTIVITY_CATALOG.md). Se agregan a medida que se implementan.
 */
enum ActivityType: string
{
    case Inoculate = 'inoculate';
    case ReadCulture = 'read_culture';
    case Identify = 'identify';
    case AstSetup = 'ast_setup';
    case AstRead = 'ast_read';
    case ValidateReport = 'validate_report';

    public function label(): string
    {
        return match ($this) {
            self::Inoculate => 'Siembra',
            self::ReadCulture => 'Lectura de cultivo',
            self::Identify => 'Identificación',
            self::AstSetup => 'Preparación de antibiograma',
            self::AstRead => 'Lectura de antibiograma',
            self::ValidateReport => 'Validación',
        };
    }
}
