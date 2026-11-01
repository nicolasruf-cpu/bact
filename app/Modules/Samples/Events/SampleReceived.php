<?php

namespace App\Modules\Samples\Events;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Models\Sample;

/**
 * Se emite dentro de la transacción de la recepción. Workflow reacciona creando
 * las actividades de siembra en la misma transacción (WORKFLOW_ENGINE.md §3).
 */
final readonly class SampleReceived
{
    public function __construct(
        public Sample $sample,
        public Priority $priority,
    ) {}
}
