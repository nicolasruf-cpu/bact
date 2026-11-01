<?php

namespace App\Modules\Patients\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum PatientAuditAction: string implements AuditAction
{
    case Registered = 'patient.registered';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registró un paciente',
        };
    }
}
