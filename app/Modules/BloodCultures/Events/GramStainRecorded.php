<?php

namespace App\Modules\BloodCultures\Events;

use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Models\GramStain;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Gram registrado. $communicate: el protocolo pide comunicarlo como resultado crítico.
 */
final readonly class GramStainRecorded
{
    public function __construct(
        public GramStain $gram,
        public BloodCultureBottle $bottle,
        public SampleStudy $sampleStudy,
        public string $summary,
        public bool $communicate,
        public int $userId,
    ) {}
}
