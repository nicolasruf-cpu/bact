<?php

namespace App\Modules\Cultures\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum CultureAuditAction: string implements AuditAction
{
    case Inoculated = 'culture.inoculated';
    case Read = 'culture.read';
    case Completed = 'culture.completed';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Inoculated => 'Sembró un cultivo',
            self::Read => 'Leyó un cultivo',
            self::Completed => 'Cerró un cultivo',
        };
    }
}
