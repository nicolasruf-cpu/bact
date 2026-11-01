<?php

namespace App\Modules\Identification\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum IdentificationAuditAction: string implements AuditAction
{
    case Identified = 'isolate.identified';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Identified => 'Identificó un aislamiento',
        };
    }
}
