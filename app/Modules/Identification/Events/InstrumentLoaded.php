<?php

namespace App\Modules\Identification\Events;

use App\Modules\Identification\Models\InstrumentRun;

/** Tarjetas cargadas en el equipo: Workflow deja pendiente el resultado. */
final readonly class InstrumentLoaded
{
    public function __construct(
        public InstrumentRun $run,
        public int $userId,
    ) {}
}
