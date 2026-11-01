<?php

namespace App\Modules\CriticalResults\Queries;

use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\CriticalResults\Services\CriticalAlertLink;

/**
 * Resultados críticos sin comunicar de la sede: se muestran en la barra superior.
 */
class OpenCriticalAlertsQuery
{
    /**
     * @return array<int, array{ulid: string, summary: string, opened_at: string, sample_study_id: int, attempts: int, url: string}>
     */
    public function forSite(int $siteId): array
    {
        return CriticalAlert::query()
            ->where('site_id', $siteId)
            ->where('status', CriticalAlertStatus::Open->value)
            ->withCount('communications')
            ->orderBy('opened_at')
            ->limit(20)
            ->get()
            ->map(fn (CriticalAlert $alert) => [
                'ulid' => $alert->ulid,
                'summary' => $alert->summary,
                'opened_at' => $alert->opened_at->toIso8601String(),
                'sample_study_id' => $alert->sample_study_id,
                'attempts' => (int) $alert->getAttribute('communications_count'),
                'url' => CriticalAlertLink::for($alert),
            ])
            ->values()
            ->all();
    }
}
