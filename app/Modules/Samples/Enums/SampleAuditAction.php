<?php

namespace App\Modules\Samples\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum SampleAuditAction: string implements AuditAction
{
    case Received = 'sample.received';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibió una muestra',
        };
    }
}
