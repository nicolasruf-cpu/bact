<?php

namespace App\Modules\CriticalResults\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum CriticalAuditAction: string implements AuditAction
{
    case Opened = 'critical.opened';
    case CommunicationAttempt = 'critical.communication';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Opened => 'Se abrió un resultado crítico',
            self::CommunicationAttempt => 'Registró una comunicación de resultado crítico',
        };
    }
}
