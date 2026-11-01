<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Frasco positivo: Workflow cancela lo pendiente del frasco y crea el Gram, y el subcultivo
 * salvo que el positivo haya salido de un repique (ahí ya hay placa con desarrollo).
 */
final readonly class BottlePositive
{
    public function __construct(
        public BloodCultureBottle $bottle,
        public SampleStudy $sampleStudy,
        public bool $needsSubculture,
        public int $userId,
    ) {}
}
