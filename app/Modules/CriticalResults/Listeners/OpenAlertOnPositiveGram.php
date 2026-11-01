<?php

namespace App\Modules\CriticalResults\Listeners;

use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Services\CriticalAlertOpener;

/**
 * Gram de un frasco positivo → resultado crítico a comunicar, si el protocolo lo indica
 * (decisión del laboratorio, 2026-11-01). Corre en la transacción del Gram.
 */
class OpenAlertOnPositiveGram
{
    public function __construct(private readonly CriticalAlertOpener $opener) {}

    public function handle(GramStainRecorded $event): void
    {
        if (! $event->communicate) {
            return;
        }

        $this->opener->open($event->sampleStudy, CriticalAlertSource::PositiveGram, "gram:{$event->gram->id}", $event->summary, $event->userId);
    }
}
