<?php

namespace App\Modules\Susceptibility\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum SusceptibilityAuditAction: string implements AuditAction
{
    case SetUp = 'ast.set_up';
    case Read = 'ast.read';
    case InterpretationOverridden = 'ast.interpretation_overridden';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::SetUp => 'Preparó un antibiograma',
            self::Read => 'Leyó un antibiograma',
            self::InterpretationOverridden => 'Modificó una interpretación automática',
        };
    }
}
