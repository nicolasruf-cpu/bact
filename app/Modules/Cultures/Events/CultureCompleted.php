<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Samples\Models\SampleStudy;

/**
 * El cultivo se cerró y el estudio quedó listo para validar. Workflow crea la validación.
 */
final readonly class CultureCompleted
{
    public function __construct(
        public Culture $culture,
        public SampleStudy $sampleStudy,
    ) {}
}
