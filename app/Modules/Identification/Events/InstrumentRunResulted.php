<?php

namespace App\Modules\Identification\Events;

use App\Modules\Identification\Models\InstrumentRun;

/** El equipo dio un resultado de la carga (identificación o antibiograma). */
final readonly class InstrumentRunResulted
{
    public function __construct(
        public InstrumentRun $run,
        public int $userId,
    ) {}
}
