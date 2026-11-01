<?php

namespace App\Modules\CriticalResults\Listeners;

use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Enums\CriticalAuditAction;
use App\Modules\CriticalResults\Events\CriticalAlertOpened;
use App\Modules\CriticalResults\Models\CriticalAlert;
use Illuminate\Support\Facades\Date;

/**
 * Gram de un frasco positivo → resultado crítico a comunicar, si el protocolo lo indica
 * (decisión del laboratorio, 2026-11-01). Corre en la transacción del Gram.
 * Idempotente por la clave de origen.
 */
class OpenAlertOnPositiveGram
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handle(GramStainRecorded $event): void
    {
        if (! $event->communicate) {
            return;
        }

        $study = $event->sampleStudy;
        $alert = CriticalAlert::query()->firstOrCreate(
            ['source_key' => "gram:{$event->gram->id}"],
            [
                'sample_study_id' => $study->id,
                'site_id' => $study->processing_site_id,
                'source' => CriticalAlertSource::PositiveGram,
                'summary' => $event->summary,
                'status' => CriticalAlertStatus::Open,
                'opened_at' => Date::now(),
            ],
        );

        if (! $alert->wasRecentlyCreated) {
            return;
        }

        $this->auditor->record(CriticalAuditAction::Opened, $alert, after: [
            'sample_study_id' => $study->id,
            'source' => CriticalAlertSource::PositiveGram->value,
            'summary' => $event->summary,
        ]);

        event(new CriticalAlertOpened($alert, $study->sample_id, $event->userId));
    }
}
