<?php

namespace App\Modules\Workflow;

use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Workflow\Listeners\CreateInoculationActivities;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Políticas de flujo: los módulos clínicos emiten eventos y Workflow crea o cierra
 * actividades en la misma transacción (listeners síncronos).
 */
class WorkflowServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SampleReceived::class, CreateInoculationActivities::class);
    }
}
