<?php

namespace App\Modules\WorkBatches\Enums;

enum WorkBatchItemStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Removed = 'removed';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Done => 'Hecho',
            self::Removed => 'Retirado',
            self::Released => 'Devuelto a la mesada',
        };
    }
}
