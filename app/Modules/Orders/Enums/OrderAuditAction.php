<?php

namespace App\Modules\Orders\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum OrderAuditAction: string implements AuditAction
{
    case Created = 'order.created';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Registró una orden',
        };
    }
}
