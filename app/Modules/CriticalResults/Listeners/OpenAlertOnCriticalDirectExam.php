<?php

namespace App\Modules\CriticalResults\Listeners;

use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Services\CriticalAlertOpener;
use App\Modules\Cultures\Events\DirectExamRecorded;

/**
 * Examen directo con microorganismos en un protocolo que lo marca como crítico (LCR,
 * decisión del laboratorio 2026-11-01) → resultado crítico a comunicar.
 */
class OpenAlertOnCriticalDirectExam
{
    public function __construct(private readonly CriticalAlertOpener $opener) {}

    public function handle(DirectExamRecorded $event): void
    {
        if (! $event->critical) {
            return;
        }

        $this->opener->open($event->sampleStudy, CriticalAlertSource::DirectExam, "direct:{$event->exam->id}", $event->summary, $event->userId);
    }
}
