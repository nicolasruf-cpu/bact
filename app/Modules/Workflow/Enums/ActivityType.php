<?php

namespace App\Modules\Workflow\Enums;

/**
 * Tipos de actividad (docs/functional/ACTIVITY_CATALOG.md). Se agregan a medida que se implementan.
 */
enum ActivityType: string
{
    case Inoculate = 'inoculate';
    case ReadCulture = 'read_culture';

    public function label(): string
    {
        return match ($this) {
            self::Inoculate => 'Siembra',
            self::ReadCulture => 'Lectura de cultivo',
        };
    }
}
