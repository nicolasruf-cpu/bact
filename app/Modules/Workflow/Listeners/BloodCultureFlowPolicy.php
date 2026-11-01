<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\BloodCultures\Events\BlindSubcultureDone;
use App\Modules\BloodCultures\Events\BlindSubcultureRead;
use App\Modules\BloodCultures\Events\BottleLoaded;
use App\Modules\BloodCultures\Events\BottleNegative;
use App\Modules\BloodCultures\Events\BottlePositive;
use App\Modules\BloodCultures\Events\BottleSubcultured;
use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\CriticalResults\Events\CriticalAlertOpened;
use App\Modules\CriticalResults\Events\CriticalCommunicationRecorded;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Services\ActivityLifecycle;
use App\Modules\Workflow\Services\ActivityScheduler;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Políticas de flujo del frasco de hemocultivo (ACTIVITY_CATALOG.md §F). Los tiempos vienen
 * en los eventos o en el frasco (calculados con el protocolo congelado); acá no hay criterios clínicos.
 * El positivo y el resultado crítico van con prioridad inmediata.
 */
class BloodCultureFlowPolicy
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly ActivityScheduler $scheduler,
    ) {}

    /**
     * Carga → equipo: descarga del negativo al cumplirse los días; manual: primer repique.
     */
    public function onBottleLoaded(BottleLoaded $event): void
    {
        $bottle = $event->bottle;
        $study = $event->sampleStudy;
        $load = $this->lifecycle->completeFor($study, ActivityType::LoadBottle, $event->userId);
        $priority = $load->priority ?? $this->priority($study);

        if ($bottle->isAutomated()) {
            $this->schedule(ActivityType::ConfirmNegativeBottle, $bottle, $study, $priority, $bottle->incubation_ends_at, causedBy: $load?->id);

            return;
        }

        $first = $bottle->loaded_at->copy()->addHours($bottle->settings()->blindSubcultureIntervalHours);
        $this->schedule(ActivityType::BlindSubculture, $bottle, $study, $priority, $first, causedBy: $load?->id);
    }

    /**
     * Repique hecho → su lectura y, si quedan días, el repique siguiente.
     */
    public function onBlindSubcultureDone(BlindSubcultureDone $event): void
    {
        $bottle = $event->bottle;
        $study = $bottle->sampleStudy;
        $done = $this->lifecycle->completeFor($bottle, ActivityType::BlindSubculture, $event->userId);
        $priority = $done->priority ?? $this->priority($study);

        $this->schedule(ActivityType::ReadBlindSubculture, $event->subculture, $study, $priority, $event->readAvailableAt, $event->readDueAt, causedBy: $done?->id);

        if ($event->nextSubcultureAt !== null) {
            $this->schedule(ActivityType::BlindSubculture, $bottle, $study, $priority, $event->nextSubcultureAt, attempt: $event->subculture->number + 1, causedBy: $done?->id);
        }
    }

    public function onBlindSubcultureRead(BlindSubcultureRead $event): void
    {
        $this->lifecycle->completeFor($event->subculture, ActivityType::ReadBlindSubculture, $event->userId);
    }

    /**
     * Positivo → se cancela lo pendiente del frasco; Gram y subcultivo con prioridad inmediata.
     */
    public function onBottlePositive(BottlePositive $event): void
    {
        $bottle = $event->bottle;
        $this->cancelPending($bottle, 'El frasco dio positivo.');

        $this->schedule(ActivityType::GramStain, $bottle, $event->sampleStudy, Priority::Stat, Date::now());
        if ($event->needsSubculture) {
            $this->schedule(ActivityType::SubcultureBottle, $bottle, $event->sampleStudy, Priority::Stat, Date::now());
        }
    }

    public function onBottleSubcultured(BottleSubcultured $event): void
    {
        $this->lifecycle->completeFor($event->bottle, ActivityType::SubcultureBottle, $event->userId);
    }

    public function onGramStainRecorded(GramStainRecorded $event): void
    {
        $this->lifecycle->completeFor($event->bottle, ActivityType::GramStain, $event->userId);
    }

    /**
     * Negativo → el estudio pasa a la bandeja de validación.
     */
    public function onBottleNegative(BottleNegative $event): void
    {
        $completed = $this->lifecycle->completeFor($event->bottle, ActivityType::ConfirmNegativeBottle, $event->userId);
        $this->cancelPending($event->bottle, 'El frasco se cerró como negativo.');

        $this->schedule(ActivityType::ValidateReport, $event->sampleStudy, $event->sampleStudy, $this->priority($event->sampleStudy), Date::now(), causedBy: $completed?->id);
    }

    public function onCriticalAlertOpened(CriticalAlertOpened $event): void
    {
        $alert = $event->alert;

        $this->scheduler->schedule(
            type: ActivityType::CommunicateCritical,
            subject: $alert,
            sampleId: $event->sampleId,
            siteId: $alert->site_id,
            priority: Priority::Stat,
            availableAt: Date::now(),
        );
    }

    public function onCriticalCommunicationRecorded(CriticalCommunicationRecorded $event): void
    {
        if ($event->communication->successful) {
            $this->lifecycle->completeFor($event->alert, ActivityType::CommunicateCritical, $event->userId);
        }
    }

    private function cancelPending(BloodCultureBottle $bottle, string $reason): void
    {
        $this->lifecycle->cancelOpenFor($bottle, [ActivityType::BlindSubculture, ActivityType::ConfirmNegativeBottle], $reason);
        foreach ($bottle->blindSubcultures()->get() as $subculture) {
            $this->lifecycle->cancelOpenFor($subculture, [ActivityType::ReadBlindSubculture], $reason);
        }
    }

    private function schedule(
        ActivityType $type,
        Model $subject,
        SampleStudy $study,
        Priority $priority,
        CarbonInterface $availableAt,
        ?CarbonInterface $dueAt = null,
        int $attempt = 1,
        ?int $causedBy = null,
    ): void {
        $this->scheduler->schedule(
            type: $type,
            subject: $subject,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $priority,
            availableAt: $availableAt,
            dueAt: $dueAt,
            attempt: $attempt,
            causedByActivityId: $causedBy,
        );
    }

    private function priority(SampleStudy $study): Priority
    {
        return Sample::query()->with('labOrder')->findOrFail($study->sample_id)->labOrder->priority;
    }
}
