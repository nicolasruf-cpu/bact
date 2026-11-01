<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;

/**
 * Caldo de enriquecimiento repicado a placas. Workflow completa el repique; las placas
 * nuevas se leen en las lecturas programadas del cultivo.
 */
final readonly class EnrichmentSubcultured
{
    public function __construct(
        public Culture $culture,
        public Plate $broth,
        public int $userId,
    ) {}
}
