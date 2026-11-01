<?php

namespace App\Modules\CriticalResults\Services;

use App\Modules\Audit\Services\Auditor;
use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Enums\CriticalAuditAction;
use App\Modules\CriticalResults\Events\CriticalAlertOpened;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Support\Facades\Date;

/**
 * Abre un resultado crítico a comunicar. Corre en la transacción de la operación que lo
 * provoca. Idempotente por la clave de origen (p. ej., "gram:12", "direct:5").
 */
class CriticalAlertOpener
{
    public function __construct(private readonly Auditor $auditor) {}

    public function open(SampleStudy $study, CriticalAlertSource $source, string $sourceKey, string $summary, int $userId): CriticalAlert
    {
        $alert = CriticalAlert::query()->firstOrCreate(
            ['source_key' => $sourceKey],
            [
                'sample_study_id' => $study->id,
                'site_id' => $study->processing_site_id,
                'source' => $source,
                'summary' => $summary,
                'status' => CriticalAlertStatus::Open,
                'opened_at' => Date::now(),
            ],
        );

        if (! $alert->wasRecentlyCreated) {
            return $alert;
        }

        $this->auditor->record(CriticalAuditAction::Opened, $alert, after: [
            'sample_study_id' => $study->id,
            'source' => $source->value,
            'summary' => $summary,
        ]);

        event(new CriticalAlertOpened($alert, $study->sample_id, $userId));

        return $alert;
    }
}
