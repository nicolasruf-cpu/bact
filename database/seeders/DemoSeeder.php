<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Configuration\Database\Seeders\BloodCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de demostración SINTÉTICOS (sedes y usuarios ficticios). Nunca en producción.
 * Contraseña de todos los usuarios: "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder no se ejecuta en producción.');
        }

        $central = Site::updateOrCreate(['code' => 'CEN'], ['name' => 'Sede Central (demo)']);
        $norte = Site::updateOrCreate(['code' => 'NOR'], ['name' => 'Sede Norte (demo)']);
        $sur = Site::updateOrCreate(['code' => 'SUR'], ['name' => 'Sede Sur (demo)']);

        $this->user('admin@bactruf.test', 'Ana Administradora', [
            [$central, Role::Admin], [$norte, Role::Admin], [$sur, Role::Admin],
        ]);
        $this->user('director.central@bactruf.test', 'Diego Director (Central)', [[$central, Role::TechnicalDirector]]);
        $this->user('director.norte@bactruf.test', 'Dora Directora (Norte)', [[$norte, Role::TechnicalDirector]]);
        $this->user('director.sur@bactruf.test', 'Darío Director (Sur)', [[$sur, Role::TechnicalDirector]]);
        $this->user('bioquimica@bactruf.test', 'Beatriz Bioquímica', [
            [$central, Role::Biochemist], [$norte, Role::Biochemist],
        ]);
        $this->user('auxiliar@bactruf.test', 'Andrés Auxiliar', [[$central, Role::Assistant]]);
        $this->user('consulta@bactruf.test', 'Carla Consulta', [[$central, Role::Viewer]]);

        // Solo en demo: la plantilla de referencia queda vigente para probar el circuito
        // con datos sintéticos. En producción se carga en borrador (D-0008).
        $this->callWith(UrineCultureTemplateSeeder::class, ['status' => ProtocolVersionStatus::Active]);
        $this->callWith(BloodCultureTemplateSeeder::class, ['status' => ProtocolVersionStatus::Active]);
        $this->call(ReferenceCatalogSeeder::class);
        // Puntos de corte FICTICIOS (no clínicos) solo para probar la interpretación.
        $this->call(SyntheticBreakpointSeeder::class);
    }

    /**
     * @param  list<array{0: Site, 1: Role}>  $assignments
     */
    private function user(string $email, string $name, array $assignments): void
    {
        $user = User::where('email', $email)->first()
            ?? User::factory()->create(['email' => $email, 'name' => $name]);

        foreach ($assignments as [$site, $role]) {
            setPermissionsTeamId($site->id);
            $user->unsetRelation('roles');
            $user->assignRole($role->value);
        }

        setPermissionsTeamId(null);
    }
}
