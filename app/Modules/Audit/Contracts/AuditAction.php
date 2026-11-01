<?php

namespace App\Modules\Audit\Contracts;

/**
 * Acción auditable. Cada módulo la implementa con un enum backed propio
 * (p. ej. `SampleAuditAction::Received = 'sample.received'`), así no hay strings sueltos.
 */
interface AuditAction
{
    public function auditName(): string;

    public function label(): string;
}
