<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Frasco cargado: Workflow programa los repiques (manual) o la descarga del negativo (equipo).
 */
final readonly class BottleLoaded
{
    public function __construct(
        public BloodCultureBottle $bottle,
        public SampleStudy $sampleStudy,
        public int $userId,
    ) {}
}
