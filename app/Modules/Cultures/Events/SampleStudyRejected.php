<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Samples\Models\SampleStudy;

/** Muestra no apta: Workflow cancela lo pendiente del estudio. */
final readonly class SampleStudyRejected
{
    public function __construct(
        public SampleStudy $sampleStudy,
        public ?Culture $culture,
        public int $userId,
    ) {}
}
