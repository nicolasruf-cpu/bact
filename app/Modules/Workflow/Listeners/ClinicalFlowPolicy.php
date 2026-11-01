<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Cultures\Events\CultureCompleted;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Cultures\Events\DirectExamRecorded;
use App\Modules\Cultures\Events\EnrichmentSubcultured;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Events\IsolateIdentified;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Reporting\Events\ReportValidated;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Events\SusceptibilityTestSetUp;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Services\ActivityLifecycle;
use App\Modules\Workflow\Services\ActivityScheduler;
use Illuminate\Support\Facades\Date;

/**
 * Políticas de flujo del cultivo en adelante (WORKFLOW_ENGINE.md §3). Cada evento clínico
 * completa su actividad y crea la siguiente, en la misma transacción. Los tiempos salen
 * de la versión de protocolo congelada; acá no hay criterios clínicos.
 */
class ClinicalFlowPolicy
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly ActivityScheduler $scheduler,
    ) {}

    /**
     * Lectura → completa la lectura; reincubación → nueva lectura; aislamientos → identificación.
     */
    public function onCultureRead(CultureRead $event): void
    {
        $culture = $event->culture;
        $study = $culture->sampleStudy;

        $reading = $this->lifecycle->completeFor($culture, ActivityType::ReadCulture, $event->userId);

        if ($event->nextReadAt !== null) {
            $this->scheduler->schedule(
                type: ActivityType::ReadCulture,
                subject: $culture,
                sampleId: $study->sample_id,
                siteId: $study->processing_site_id,
                priority: $this->priority($study),
                availableAt: $event->nextReadAt,
                dueAt: $event->nextReadDueAt,
                attempt: $event->reading->attempt + 1,
                causedByActivityId: $reading?->id,
            );
        }

        foreach ($event->significantIsolates as $isolate) {
            $this->scheduleFor($isolate, ActivityType::Identify, $reading?->id);
        }
    }

    public function onDirectExamRecorded(DirectExamRecorded $event): void
    {
        $this->lifecycle->completeFor($event->sampleStudy, ActivityType::DirectExam, $event->userId);
    }

    public function onEnrichmentSubcultured(EnrichmentSubcultured $event): void
    {
        $this->lifecycle->completeFor($event->broth, ActivityType::SubcultureEnrichment, $event->userId);
    }

    /**
     * Identificación definitiva → completa la identificación; si corresponde, preparar el ATB.
     */
    public function onIsolateIdentified(IsolateIdentified $event): void
    {
        $identify = $this->lifecycle->completeFor($event->isolate, ActivityType::Identify, $event->userId);

        if ($event->requiresSusceptibility) {
            $this->scheduleFor($event->isolate, ActivityType::AstSetup, $identify?->id);
        }
    }

    /**
     * ATB preparado → lectura del ATB al cumplirse la incubación del protocolo.
     */
    public function onSusceptibilitySetUp(SusceptibilityTestSetUp $event): void
    {
        $isolate = $event->test->isolate;
        $setup = $this->lifecycle->completeFor($isolate, ActivityType::AstSetup, $event->userId);

        $study = $isolate->culture->sampleStudy;
        $version = $study->protocolVersion;
        $setUpAt = $event->test->set_up_at;

        $this->scheduler->schedule(
            type: ActivityType::AstRead,
            subject: $event->test,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: $setUpAt->copy()->addHours((int) $version->ast_read_min_hours),
            dueAt: $version->ast_read_due_hours !== null ? $setUpAt->copy()->addHours($version->ast_read_due_hours) : null,
            causedByActivityId: $setup?->id,
        );
    }

    public function onSusceptibilityRead(SusceptibilityTestRead $event): void
    {
        $this->lifecycle->completeFor($event->test, ActivityType::AstRead, $event->userId);
    }

    /**
     * Cultivo cerrado → el estudio pasa a la bandeja de validación.
     */
    public function onCultureCompleted(CultureCompleted $event): void
    {
        $study = $event->sampleStudy;

        $this->scheduler->schedule(
            type: ActivityType::ValidateReport,
            subject: $study,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: Date::now(),
        );
    }

    public function onReportValidated(ReportValidated $event): void
    {
        $this->lifecycle->completeFor($event->sampleStudy, ActivityType::ValidateReport, $event->userId);
    }

    private function scheduleFor(Isolate $isolate, ActivityType $type, ?int $causedBy): void
    {
        $study = $isolate->culture->sampleStudy;

        $this->scheduler->schedule(
            type: $type,
            subject: $isolate,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $this->priority($study),
            availableAt: Date::now(),
            causedByActivityId: $causedBy,
        );
    }

    private function priority(SampleStudy $study): Priority
    {
        return Sample::query()->with('labOrder')->findOrFail($study->sample_id)->labOrder->priority;
    }
}
