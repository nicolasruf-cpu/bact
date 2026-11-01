<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Cultures\Events\CultureInoculated;
use App\Modules\Samples\Models\Sample;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Services\ActivityLifecycle;
use App\Modules\Workflow\Services\ActivityScheduler;

/**
 * Política: CultureInoculated → completa la siembra y programa la primera lectura
 * del cronograma de la versión de protocolo congelada (disponible desde el mínimo,
 * vence en el máximo), y el repique de cada caldo de enriquecimiento.
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
        // Sin siembra previa (p. ej., subcultivo de un hemocultivo): la prioridad de la orden.
        $priority = $inoculation !== null
            ? $inoculation->priority
            : Sample::query()->with('labOrder')->findOrFail($study->sample_id)->labOrder->priority;

        $inoculatedAt = $event->culture->inoculated_at;
        $first = $version->readingSchedule()[0];

        $this->scheduler->schedule(
            type: ActivityType::ReadCulture,
            subject: $event->culture,
            sampleId: $study->sample_id,
            siteId: $study->processing_site_id,
            priority: $priority,
            availableAt: $inoculatedAt->copy()->addHours($first['min_hours']),
            dueAt: $inoculatedAt->copy()->addHours($first['due_hours']),
            causedByActivityId: $inoculation?->id,
        );

        $media = $version->media->keyBy('medium_id');
        foreach ($event->culture->plates()->where('is_enrichment', true)->get() as $broth) {
            /** @var ProtocolVersionMedium|null $setting */
            $setting = $media->get($broth->medium_id);
            $this->scheduler->schedule(
                type: ActivityType::SubcultureEnrichment,
                subject: $broth,
                sampleId: $study->sample_id,
                siteId: $study->processing_site_id,
                priority: $priority,
                availableAt: $inoculatedAt->copy()->addHours((int) $setting?->enrichment_subculture_min_hours),
                dueAt: $setting?->enrichment_subculture_due_hours !== null ? $inoculatedAt->copy()->addHours($setting->enrichment_subculture_due_hours) : null,
                causedByActivityId: $inoculation?->id,
            );
        }
    }
}
