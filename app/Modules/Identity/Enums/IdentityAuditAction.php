<?php

namespace App\Modules\Identity\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum IdentityAuditAction: string implements AuditAction
{
    case LoggedIn = 'identity.logged_in';
    case LoggedOut = 'identity.logged_out';
    case LoginFailed = 'identity.login_failed';
    case ActiveSiteSwitched = 'identity.active_site_switched';
    case UserCreated = 'identity.user_created';
    case UserUpdated = 'identity.user_updated';
    case UserRolesChanged = 'identity.user_roles_changed';
    case UserPasswordReset = 'identity.user_password_reset';
    case UserDeactivated = 'identity.user_deactivated';
    case UserReactivated = 'identity.user_reactivated';
    case ClinicalApproverDesignated = 'identity.clinical_approver_designated';
    case InactiveUserBlocked = 'identity.inactive_user_blocked';

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
            self::UserCreated => 'Alta de usuario',
            self::UserUpdated => 'Modificación de usuario',
            self::UserRolesChanged => 'Cambio de perfiles por sede',
            self::UserPasswordReset => 'Contraseña restablecida por el Administrador',
            self::UserDeactivated => 'Usuario dado de baja',
            self::UserReactivated => 'Usuario reactivado',
            self::ClinicalApproverDesignated => 'Designación del responsable de la configuración clínica',
            self::InactiveUserBlocked => 'Ingreso bloqueado: usuario dado de baja',
        };
    }
}
