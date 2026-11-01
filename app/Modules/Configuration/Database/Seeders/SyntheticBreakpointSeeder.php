<?php

namespace App\Modules\Configuration\Database\Seeders;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\BreakpointSet;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * SOLO DEMO: puntos de corte FICTICIOS para poder probar la interpretación.
 * Los valores son uniformes y sin significado clínico (difusión S ≥ 20 / R ≤ 14 mm;
 * CIM S ≤ 1 / R ≥ 4 µg/mL para todo). Nunca se cargan en producción.
 * Los puntos de corte reales los carga el laboratorio desde su edición del CLSI M100 (Q-C4).
 */
class SyntheticBreakpointSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los puntos de corte ficticios no se cargan en producción.');
        }

        $set = BreakpointSet::firstOrCreate(
            ['name' => 'DEMO — valores ficticios, NO CLÍNICOS'],
            ['edition' => 'FICTICIO', 'status' => ProtocolVersionStatus::Active, 'is_synthetic' => true],
        );

        if ($set->breakpoints()->exists()) {
            return;
        }

        foreach (AstPanel::with('antibiotics')->get() as $panel) {
            foreach ($panel->antibiotics as $antibiotic) {
                foreach ([[AstMethod::Disk, 20, 14], [AstMethod::Mic, 1, 4]] as [$method, $s, $r]) {
                    $set->breakpoints()->firstOrCreate(
                        ['organism_group' => $panel->organism_group, 'antibiotic_id' => $antibiotic->id, 'method' => $method],
                        ['susceptible_value' => $s, 'resistant_value' => $r],
                    );
                }
            }
        }
    }
}
