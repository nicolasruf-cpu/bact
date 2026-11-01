<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Services\ActivityScheduler;

/**
 * Política: SampleReceived → una siembra (INOCULATE) por estudio, o la carga del frasco
 * (LOAD_BOTTLE) si es un hemocultivo, lista de inmediato, y el examen directo si el
 * protocolo lo incluye (inmediato si el protocolo lo marca urgente, p. ej. LCR),
 * en la sede que procesa el estudio. Corre en la misma transacción que la recepción.
 * Sin vencimiento hasta que el laboratorio defina tiempos objetivo (Q-C6).
 */
class CreateInoculationActivities
{
    public function __construct(private readonly ActivityScheduler $scheduler) {}

    public function handle(SampleReceived $event): void
    {
        $sample = $event->sample;

        foreach ($sample->studies()->with('protocolVersion')->get() as $study) {
            $this->scheduler->schedule(
                // Los hemocultivos no se siembran: se cargan como frasco.
                type: $study->protocolVersion->isBloodCulture() ? ActivityType::LoadBottle : ActivityType::Inoculate,
                subject: $study,
                sampleId: $sample->id,
                siteId: $study->processing_site_id,
                priority: $event->priority,
                availableAt: $sample->received_at,
            );

            $directExam = $study->protocolVersion->directExamSettings();
            if ($directExam !== null) {
                $this->scheduler->schedule(
                    type: ActivityType::DirectExam,
                    subject: $study,
                    sampleId: $sample->id,
                    siteId: $study->processing_site_id,
                    priority: $directExam->urgent ? Priority::Stat : $event->priority,
                    availableAt: $sample->received_at,
                );
            }
        }
    }
}
