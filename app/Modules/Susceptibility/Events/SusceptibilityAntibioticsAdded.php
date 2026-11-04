<?php

namespace App\Modules\Susceptibility\Events;

use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Carbon\CarbonInterface;

/** Antibióticos agregados al leer el panel: tienen su propia lectura (D-0022). */
final readonly class SusceptibilityAntibioticsAdded
{
    public function __construct(
        public SusceptibilityTest $test,
        public int $userId,
        public CarbonInterface $setUpAt,
    ) {}
}
