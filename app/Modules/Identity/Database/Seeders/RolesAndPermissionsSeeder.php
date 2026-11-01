<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos y los roles globales (site_id = null): un rol se define una vez
 * y se asigna a cada usuario en una o varias sedes. Es idempotente.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        // Con WithoutModelEvents el paquete no limpia su caché solo.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $previousSite = getPermissionsTeamId();
        setPermissionsTeamId(null);

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p) => $p->value, $role->permissions()));
        }

        setPermissionsTeamId($previousSite);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
