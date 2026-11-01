<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Data\UserData;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\SiteRoles;
use App\Modules\Organization\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Alta o modificación de un usuario con sus perfiles por sede (users.manage).
 * El Administrador solo toca los perfiles de las sedes donde administra usuarios; los
 * de otras sedes quedan como estaban. Nadie cambia sus propios perfiles.
 */
class SaveUser
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly SiteRoles $siteRoles,
        private readonly DesignateClinicalApprover $approver,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, ?User $user, UserData $data): User
    {
        $manageable = $this->siteRoles->sitesWhere($actor, Permission::UsersManage);
        if ($manageable === []) {
            throw new AuthorizationException('No tenés permiso para administrar usuarios.');
        }

        return DB::transaction(function () use ($actor, $user, $data, $manageable) {
            $email = mb_strtolower(trim($data->email));
            $duplicate = User::query()->where('email', $email)->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['email' => 'Ya hay un usuario con ese email.']);
            }

            $this->assertAssignments($data->assignments, $manageable);

            if ($user === null) {
                return $this->create($data, $email, $manageable);
            }

            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($data->lockVersion !== null && $data->lockVersion !== $user->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este usuario. Recargá la página.']);
            }

            return $this->update($actor, $user, $data, $email, $manageable);
        });
    }

    /**
     * @param  list<int>  $manageable
     */
    private function create(UserData $data, string $email, array $manageable): User
    {
        if ($data->password === null || $data->password === '') {
            throw ValidationException::withMessages(['password' => 'Ingresá una contraseña inicial.']);
        }
        if ($data->assignments === []) {
            throw ValidationException::withMessages(['assignments' => 'Asigná al menos un perfil en una sede.']);
        }

        $user = new User;
        $user->forceFill([
            'name' => trim($data->name),
            'email' => $email,
            'password' => Hash::make($data->password),
            // La cuenta la crea el Administrador: no hace falta verificar el email (Q-A9).
            'email_verified_at' => Date::now(),
            'is_active' => true,
        ])->save();

        $this->syncRoles($user, $data->assignments, $manageable);

        $this->auditor->record(IdentityAuditAction::UserCreated, $user, after: [
            'name' => $user->name,
            'email' => $user->email,
            'assignments' => $this->describe($data->assignments),
        ]);

        return $user;
    }

    /**
     * @param  list<int>  $manageable
     */
    private function update(User $actor, User $user, UserData $data, string $email, array $manageable): User
    {
        $before = ['name' => $user->name, 'email' => $user->email];
        $after = ['name' => trim($data->name), 'email' => $email];
        $changed = array_diff_assoc($after, $before);

        $currentAssignments = $this->siteRoles->assignmentsOf($user);
        $current = $this->describe(array_values(array_filter($currentAssignments, fn (array $a) => in_array($a['site_id'], $manageable, true))));
        $wanted = $this->describe($data->assignments);
        $rolesChanged = $current !== $wanted;

        if ($rolesChanged && $actor->is($user)) {
            throw ValidationException::withMessages(['assignments' => 'No podés cambiar tus propios perfiles: pedíselo a otro Administrador.']);
        }

        $passwordChanged = $data->password !== null && $data->password !== '';

        if ($changed === [] && ! $rolesChanged && ! $passwordChanged) {
            return $user;
        }

        $user->forceFill([
            ...$after,
            ...($passwordChanged ? ['password' => Hash::make((string) $data->password)] : []),
            'lock_version' => $user->lock_version + 1,
        ])->save();

        if ($changed !== []) {
            $this->auditor->record(IdentityAuditAction::UserUpdated, $user,
                before: array_intersect_key($before, $changed),
                after: $changed,
            );
        }

        if ($passwordChanged) {
            // Nunca se registra la contraseña.
            $this->auditor->record(IdentityAuditAction::UserPasswordReset, $user);
        }

        if ($rolesChanged) {
            $this->syncRoles($user, $data->assignments, $manageable);
            $this->auditor->record(IdentityAuditAction::UserRolesChanged, $user,
                before: ['assignments' => $current],
                after: ['assignments' => $wanted],
            );

            if ($user->is_clinical_approver && ! $this->siteRoles->hasRoleAnywhere($user, Role::TechnicalDirector)) {
                $this->approver->clear($user, 'Dejó de ser Director técnico en todas las sedes.');
            }
        }

        return $user;
    }

    /**
     * @param  list<array{site_id: int, role: Role}>  $assignments
     * @param  list<int>  $manageable
     */
    private function syncRoles(User $user, array $assignments, array $manageable): void
    {
        foreach ($manageable as $siteId) {
            $roles = array_values(array_map(
                fn (array $a) => $a['role'],
                array_filter($assignments, fn (array $a) => $a['site_id'] === $siteId),
            ));
            $this->siteRoles->syncSite($user, $siteId, $roles);
        }
    }

    /**
     * @param  list<array{site_id: int, role: Role}>  $assignments
     * @param  list<int>  $manageable
     *
     * @throws ValidationException
     */
    private function assertAssignments(array $assignments, array $manageable): void
    {
        $activeSites = Site::query()->active()->pluck('id')->all();

        foreach ($assignments as $assignment) {
            if (! in_array($assignment['site_id'], $manageable, true)) {
                throw ValidationException::withMessages(['assignments' => 'Solo podés asignar perfiles en las sedes donde administrás usuarios.']);
            }
            if (! in_array($assignment['site_id'], $activeSites, true)) {
                throw ValidationException::withMessages(['assignments' => 'La sede no está activa.']);
            }
        }
    }

    /**
     * Forma estable para comparar y auditar: "SITE_ID:rol" ordenado.
     *
     * @param  list<array{site_id: int, role: Role}>  $assignments
     * @return list<string>
     */
    private function describe(array $assignments): array
    {
        $items = array_values(array_unique(array_map(fn (array $a) => $a['site_id'].':'.$a['role']->value, $assignments)));
        sort($items);

        return $items;
    }
}
