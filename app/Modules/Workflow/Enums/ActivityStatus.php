<?php

namespace App\Modules\Workflow\Enums;

/**
 * Estados de actividad (WORKFLOW_ENGINE.md §2). "Demorada" no es un estado: es due_at vencido.
 */
enum ActivityStatus: string
{
    case Scheduled = 'scheduled';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::Ready => 'Lista',
            self::InProgress => 'En proceso',
            self::OnHold => 'En espera',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Ready, self::Cancelled],
            self::Ready => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::Completed, self::Ready, self::OnHold, self::Cancelled],
            self::OnHold => [self::Ready, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
