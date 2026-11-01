<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Models\Activity;

/**
 * Una actividad se completó (dentro de la transacción de la operación clínica).
 * WorkBatches marca el ítem de tanda como hecho.
 */
final readonly class ActivityCompleted
{
    public function __construct(public Activity $activity) {}
}
