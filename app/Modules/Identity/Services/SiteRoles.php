<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Perfiles de un usuario en cada sede (ADR-0008), leídos y escritos sin depender de la
 * sede activa de la sesión.
 */
class SiteRoles
{
    /**
     * @return list<array{site_id: int, role: Role}>
     */
    public function assignmentsOf(User $user): array
    {
        $rows = DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $user->getMorphClass())
            ->where('mhr.'.config('permission.column_names.model_morph_key'), $user->getKey())
            ->orderBy('mhr.'.$this->siteColumn())
            ->orderBy('r.name')
            ->get(['mhr.'.$this->siteColumn().' as site_id', 'r.name']);

        $assignments = [];
        foreach ($rows as $row) {
            $role = Role::tryFrom((string) $row->name);
            if ($role !== null) {
                $assignments[] = ['site_id' => (int) $row->site_id, 'role' => $role];
            }
        }

        return $assignments;
    }

    /**
     * Sedes donde el usuario tiene un perfil con ese permiso.
     *
     * @return list<int>
     */
    public function sitesWhere(User $user, Permission $permission): array
    {
        $roles = array_map(
            fn (Role $role) => $role->value,
            array_values(array_filter(Role::cases(), fn (Role $role) => in_array($permission, $role->permissions(), true))),
        );

        return array_values(array_unique(array_map(
            fn (array $assignment) => $assignment['site_id'],
            array_filter($this->assignmentsOf($user), fn (array $assignment) => in_array($assignment['role']->value, $roles, true)),
        )));
    }

    public function hasRoleAnywhere(User $user, Role $role): bool
    {
        foreach ($this->assignmentsOf($user) as $assignment) {
            if ($assignment['role'] === $role) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deja exactamente esos perfiles del usuario en la sede.
     *
     * @param  list<Role>  $roles
     */
    public function syncSite(User $user, int $siteId, array $roles): void
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($siteId);

        try {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles(array_map(fn (Role $role) => $role->value, $roles));
        } finally {
            setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function siteColumn(): string
    {
        return (string) config('permission.column_names.team_foreign_key');
    }
}
