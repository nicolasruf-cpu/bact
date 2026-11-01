<?php

namespace App\Modules\Reporting\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum ReportAuditAction: string implements AuditAction
{
    case Validated = 'report.validated';
    case PreliminaryIssued = 'report.preliminary_issued';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Validó un informe',
            self::PreliminaryIssued => 'Emitió un informe preliminar',
        };
    }
}
