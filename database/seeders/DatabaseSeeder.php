<?php

namespace Database\Seeders;

use App\Modules\Configuration\Database\Seeders\BloodCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->isProduction()) {
            // Plantilla en BORRADOR: no se usa hasta que la apruebe el Director técnico (D-0008).
            $this->call(UrineCultureTemplateSeeder::class);
            $this->call(BloodCultureTemplateSeeder::class);
            $this->call(ReferenceCatalogSeeder::class);

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
