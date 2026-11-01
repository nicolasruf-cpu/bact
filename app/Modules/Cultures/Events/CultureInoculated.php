<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Se emite dentro de la transacción de la siembra. Workflow completa la siembra
 * y programa la lectura según la versión de protocolo congelada.
 */
final readonly class CultureInoculated
{
    public function __construct(
        public Culture $culture,
        public SampleStudy $sampleStudy,
        public int $userId,
    ) {}
}
