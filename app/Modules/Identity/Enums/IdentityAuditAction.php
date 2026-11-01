<?php

namespace App\Modules\Identity\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum IdentityAuditAction: string implements AuditAction
{
    case LoggedIn = 'identity.logged_in';
    case LoggedOut = 'identity.logged_out';
    case LoginFailed = 'identity.login_failed';
    case ActiveSiteSwitched = 'identity.active_site_switched';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::LoggedIn => 'Ingresó al sistema',
            self::LoggedOut => 'Cerró sesión',
            self::LoginFailed => 'Intento de ingreso fallido',
            self::ActiveSiteSwitched => 'Cambió de sede',
        };
    }
}
