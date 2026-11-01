<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;

/**
 * Repique leído (el desarrollo además emite BottlePositive; el último sin desarrollo, BottleNegative).
 */
final readonly class BlindSubcultureRead
{
    public function __construct(
        public BlindSubculture $subculture,
        public BloodCultureBottle $bottle,
        public int $userId,
    ) {}
}
