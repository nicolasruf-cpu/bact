<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Frasco cerrado como negativo: el estudio queda listo para validar.
 */
final readonly class BottleNegative
{
    public function __construct(
        public BloodCultureBottle $bottle,
        public SampleStudy $sampleStudy,
        public int $userId,
    ) {}
}
