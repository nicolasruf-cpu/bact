<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Cultures\Events\CultureInoculated;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use App\Modules\Workflow\Services\ActivityLifecycle;
use App\Modules\Workflow\Services\ActivityScheduler;

/**
 * Política: CultureInoculated → completa la siembra y programa la primera lectura
 * con los tiempos de la versión de protocolo congelada (disponible desde el mínimo,
 * vence en el máximo).
 */
class ScheduleCultureReading
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly ActivityScheduler $scheduler,
    ) {}

    public function handle(CultureInoculated $event): void
    {
        $study = $event->sampleStudy;
        $version = $study->protocolVersion;

        $inoculation = $this->lifecycle->completeFor($study, ActivityType::Inoculate, $event->userId);
        $priority = $inoculation !== null
            ? $inoculation->priority
            : (Priority::tryFrom((string) Activity::query()->where('sample_id', $study->sample_id)->value('priority')) ?? Priority::Routine);

        $inoculatedAt = $event->culture->inoculated_at;

        $this->scheduler->schedule(
            type: ActivityType::ReadCulture,
            subject: $event->culture,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $priority,
            availableAt: $inoculatedAt->copy()->addHours($version->first_read_min_hours),
            dueAt: $inoculatedAt->copy()->addHours($version->first_read_due_hours),
            causedByActivityId: $inoculation?->id,
        );
    }
}
