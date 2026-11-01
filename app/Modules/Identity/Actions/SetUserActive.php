<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Services\SiteRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Baja o reactivación de un usuario. Nunca se borra: su nombre sigue en la auditoría y
 * en los registros clínicos que firmó. Una baja corta las sesiones abiertas.
 */
class SetUserActive
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly SiteRoles $siteRoles,
        private readonly DesignateClinicalApprover $approver,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, User $user, bool $active, string $reason, int $lockVersion): User
    {
        if ($this->siteRoles->sitesWhere($actor, Permission::UsersManage) === []) {
            throw new AuthorizationException('No tenés permiso para administrar usuarios.');
        }
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['reason' => 'No podés darte de baja a vos mismo.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indicá el motivo.']);
        }

        return DB::transaction(function () use ($user, $active, $reason, $lockVersion) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->is_active === $active) {
                return $user;
            }
            if ($user->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este usuario. Recargá la página.']);
            }

            $user->forceFill(['is_active' => $active, 'lock_version' => $user->lock_version + 1])->save();

            $this->auditor->record(
                $active ? IdentityAuditAction::UserReactivated : IdentityAuditAction::UserDeactivated,
                $user,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                reason: trim($reason),
            );

            if (! $active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $this->approver->clear($user, 'Usuario dado de baja.');
            }

            return $user;
        });
    }
}
