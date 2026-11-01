<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use Carbon\CarbonInterface;

/**
 * Repique hecho: Workflow programa su lectura y, si quedan días de incubación, el próximo repique.
 */
final readonly class BlindSubcultureDone
{
    public function __construct(
        public BlindSubculture $subculture,
        public BloodCultureBottle $bottle,
        public CarbonInterface $readAvailableAt,
        public ?CarbonInterface $readDueAt,
        public ?CarbonInterface $nextSubcultureAt,
        public int $userId,
    ) {}
}
