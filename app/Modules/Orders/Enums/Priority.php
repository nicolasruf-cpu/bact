<?php

namespace App\Modules\Orders\Enums;

/**
 * Prioridad de la solicitud; las actividades la heredan (WORKFLOW_ENGINE.md §5).
 */
enum Priority: string
{
    case Routine = 'routine';
    case Urgent = 'urgent';
    case Stat = 'stat';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Rutina',
            self::Urgent => 'Urgente',
            self::Stat => 'Inmediata',
        };
    }

    /**
     * Mayor número = más prioritario (para ordenar).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Routine => 0,
            self::Urgent => 1,
            self::Stat => 2,
        };
    }
}
