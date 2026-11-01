<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\SiteRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Designa al Director técnico responsable de aprobar la configuración clínica compartida
 * (Q-A8). Hay uno solo: designar a otro le quita la designación al anterior.
 * Lo hace el Administrador (users.manage) y queda auditado con su motivo.
 */
class DesignateClinicalApprover
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly SiteRoles $siteRoles,
    ) {}

    /**
     * @param  User|null  $user  null = nadie queda designado
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, ?User $user, string $reason): ?User
    {
        if ($this->siteRoles->sitesWhere($actor, Permission::UsersManage) === []) {
            throw new AuthorizationException('No tenés permiso para administrar usuarios.');
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indicá el motivo de la designación.']);
        }

        return DB::transaction(function () use ($user, $reason) {
            /** @var User|null $current */
            $current = User::query()->where('is_clinical_approver', true)->lockForUpdate()->first();

            if ($user !== null) {
                /** @var User $user */
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($current?->is($user)) {
                    return $user;
                }
                if (! $user->is_active) {
                    throw ValidationException::withMessages(['user_id' => 'El usuario está dado de baja.']);
                }
                if (! $this->siteRoles->hasRoleAnywhere($user, Role::TechnicalDirector)) {
                    throw ValidationException::withMessages(['user_id' => 'El responsable tiene que ser Director técnico en alguna sede.']);
                }
            } elseif ($current === null) {
                return null;
            }

            if ($current !== null) {
                $current->forceFill(['is_clinical_approver' => false, 'lock_version' => $current->lock_version + 1])->save();
            }
            $user?->forceFill(['is_clinical_approver' => true, 'lock_version' => $user->lock_version + 1])->save();

            $this->auditor->record(IdentityAuditAction::ClinicalApproverDesignated, $user ?? $current,
                before: ['user_id' => $current?->id, 'name' => $current?->name],
                after: ['user_id' => $user?->id, 'name' => $user?->name],
                reason: trim($reason),
            );

            return $user;
        });
    }

    /**
     * Quita la designación cuando el responsable deja de cumplir las condiciones (baja,
     * sin perfil de Director técnico). Se llama dentro de la transacción de esa operación.
     */
    public function clear(User $user, string $reason): void
    {
        if (! $user->is_clinical_approver) {
            return;
        }

        $user->forceFill(['is_clinical_approver' => false, 'lock_version' => $user->lock_version + 1])->save();

        $this->auditor->record(IdentityAuditAction::ClinicalApproverDesignated, $user,
            before: ['user_id' => $user->id, 'name' => $user->name],
            after: ['user_id' => null, 'name' => null],
            reason: $reason,
        );
    }
}
