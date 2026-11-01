<?php

namespace App\Modules\WorkBatches\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum WorkBatchAuditAction: string implements AuditAction
{
    case Started = 'batch.started';
    case Paused = 'batch.paused';
    case Resumed = 'batch.resumed';
    case ItemRemoved = 'batch.item_removed';
    case Finished = 'batch.finished';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Started => 'Inició una tanda',
            self::Paused => 'Pausó una tanda',
            self::Resumed => 'Reanudó una tanda',
            self::ItemRemoved => 'Retiró un ítem de una tanda',
            self::Finished => 'Finalizó una tanda',
        };
    }
}
