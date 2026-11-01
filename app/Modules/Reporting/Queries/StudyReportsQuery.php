<?php

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Models\Report;

/**
 * Informes emitidos de un estudio (preliminares y definitivo), del más nuevo al más viejo.
 */
class StudyReportsQuery
{
    /**
     * @return array<int, array{ulid: string, version: int, type: string, type_label: string, status: string, status_label: string, validated_at: string, url: string}>
     */
    public function forStudy(int $sampleStudyId): array
    {
        return Report::query()
            ->where('sample_study_id', $sampleStudyId)
            ->orderByDesc('version')
            ->get()
            ->map(fn (Report $report) => [
                'ulid' => $report->ulid,
                'version' => $report->version,
                'type' => $report->type->value,
                'type_label' => $report->type->label(),
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
                'validated_at' => $report->validated_at->toIso8601String(),
                'url' => route('reports.show', $report),
            ])
            ->values()
            ->all();
    }
}
