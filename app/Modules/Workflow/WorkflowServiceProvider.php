<?php

namespace App\Modules\Workflow;

use App\Modules\BloodCultures\Events\BlindSubcultureDone;
use App\Modules\BloodCultures\Events\BlindSubcultureRead;
use App\Modules\BloodCultures\Events\BottleLoaded;
use App\Modules\BloodCultures\Events\BottleNegative;
use App\Modules\BloodCultures\Events\BottlePositive;
use App\Modules\BloodCultures\Events\BottleSubcultured;
use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\CriticalResults\Events\CriticalAlertOpened;
use App\Modules\CriticalResults\Events\CriticalCommunicationRecorded;
use App\Modules\Cultures\Events\CultureCompleted;
use App\Modules\Cultures\Events\CultureInoculated;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Cultures\Events\DirectExamRecorded;
use App\Modules\Cultures\Events\EnrichmentSubcultured;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Reporting\Events\ReportValidated;
use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Workflow\Console\ReleaseDueActivities;
use App\Modules\Workflow\Listeners\BloodCultureFlowPolicy;
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
        Event::listen(DirectExamRecorded::class, [ClinicalFlowPolicy::class, 'onDirectExamRecorded']);
        Event::listen(EnrichmentSubcultured::class, [ClinicalFlowPolicy::class, 'onEnrichmentSubcultured']);
        Event::listen(IsolateIdentified::class, [ClinicalFlowPolicy::class, 'onIsolateIdentified']);
        Event::listen(SusceptibilityTestSetUp::class, [ClinicalFlowPolicy::class, 'onSusceptibilitySetUp']);
        Event::listen(SusceptibilityTestRead::class, [ClinicalFlowPolicy::class, 'onSusceptibilityRead']);
        Event::listen(CultureCompleted::class, [ClinicalFlowPolicy::class, 'onCultureCompleted']);
        Event::listen(ReportValidated::class, [ClinicalFlowPolicy::class, 'onReportValidated']);

        // Hemocultivo y resultados críticos.
        Event::listen(BottleLoaded::class, [BloodCultureFlowPolicy::class, 'onBottleLoaded']);
        Event::listen(BlindSubcultureDone::class, [BloodCultureFlowPolicy::class, 'onBlindSubcultureDone']);
        Event::listen(BlindSubcultureRead::class, [BloodCultureFlowPolicy::class, 'onBlindSubcultureRead']);
        Event::listen(BottlePositive::class, [BloodCultureFlowPolicy::class, 'onBottlePositive']);
        Event::listen(BottleSubcultured::class, [BloodCultureFlowPolicy::class, 'onBottleSubcultured']);
        Event::listen(GramStainRecorded::class, [BloodCultureFlowPolicy::class, 'onGramStainRecorded']);
        Event::listen(BottleNegative::class, [BloodCultureFlowPolicy::class, 'onBottleNegative']);
        Event::listen(CriticalAlertOpened::class, [BloodCultureFlowPolicy::class, 'onCriticalAlertOpened']);
        Event::listen(CriticalCommunicationRecorded::class, [BloodCultureFlowPolicy::class, 'onCriticalCommunicationRecorded']);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseDueActivities::class]);
        }
    }
}
