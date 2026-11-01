<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Services\ActivityScheduler;

/**
 * Política: SampleReceived → una siembra (INOCULATE) por estudio, lista de inmediato,
 * en la sede que procesa el estudio. Corre en la misma transacción que la recepción.
 * Sin vencimiento hasta que el laboratorio defina tiempos objetivo (Q-C6).
 */
class CreateInoculationActivities
{
    public function __construct(private readonly ActivityScheduler $scheduler) {}

    public function handle(SampleReceived $event): void
    {
        $sample = $event->sample;

        foreach ($sample->studies()->get() as $study) {
            $this->scheduler->schedule(
                type: ActivityType::Inoculate,
                subject: $study,
                sampleId: $sample->id,
                siteId: $study->processing_site_id,
                priority: $event->priority,
                availableAt: $sample->received_at,
            );
        }
    }
}
