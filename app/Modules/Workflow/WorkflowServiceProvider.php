<?php

namespace App\Modules\Workflow;

use App\Modules\Cultures\Events\CultureCompleted;
use App\Modules\Cultures\Events\CultureInoculated;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Reporting\Events\ReportValidated;
use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Workflow\Console\ReleaseDueActivities;
use App\Modules\Workflow\Listeners\ClinicalFlowPolicy;
use App\Modules\Workflow\Listeners\CreateInoculationActivities;
use App\Modules\Workflow\Listeners\ScheduleCultureReading;
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
        Event::listen(CultureInoculated::class, ScheduleCultureReading::class);
        Event::listen(CultureRead::class, [ClinicalFlowPolicy::class, 'onCultureRead']);
        Event::listen(IsolateIdentified::class, [ClinicalFlowPolicy::class, 'onIsolateIdentified']);
        Event::listen(SusceptibilityTestSetUp::class, [ClinicalFlowPolicy::class, 'onSusceptibilitySetUp']);
        Event::listen(SusceptibilityTestRead::class, [ClinicalFlowPolicy::class, 'onSusceptibilityRead']);
        Event::listen(CultureCompleted::class, [ClinicalFlowPolicy::class, 'onCultureCompleted']);
        Event::listen(ReportValidated::class, [ClinicalFlowPolicy::class, 'onReportValidated']);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseDueActivities::class]);
        }
    }
}
