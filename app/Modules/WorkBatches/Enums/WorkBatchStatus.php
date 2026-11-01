<?php

namespace App\Modules\WorkBatches\Enums;

/**
 * Estados de la tanda (WORK_BATCHES.md §2).
 */
enum WorkBatchStatus: string
{
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En proceso',
            self::Paused => 'En pausa',
            self::Completed => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::InProgress || $this === self::Paused;
    }
}
