<?php

namespace App\Modules\Workflow\Queries;

use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mesada de siembra: actividades INOCULATE de la sede (WORKBENCHES.md).
 * Orden: prioridad (inmediata > urgente > rutina) → más antiguas primero.
 */
class InoculationWorkbenchQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(int $siteId): array
    {
        return $this->open($siteId)
            ->with([
                'sample.labOrder.patient',
                'sample.sampleType',
                'subject' => fn ($morph) => $morph->morphWith([
                    SampleStudy::class => ['study', 'protocolVersion.media.medium', 'protocolVersion.protocol'],
                ]),
            ])
            ->orderByRaw("FIELD(priority, 'stat', 'urgent', 'routine')")
            ->orderBy('available_at')
            ->limit(500)
            ->get()
            ->map(function (Activity $activity) {
                /** @var SampleStudy $study */
                $study = $activity->subject;
                $version = $study->protocolVersion;

                return [
                    'ulid' => $activity->ulid,
                    'status' => $activity->status->value,
                    'priority' => $activity->priority->value,
                    'priority_label' => $activity->priority->label(),
                    'available_at' => $activity->available_at->toIso8601String(),
                    'barcode' => $activity->sample->barcode,
                    'patient' => $activity->sample->labOrder->patient->fullName(),
                    'sample_type' => $activity->sample->sampleType->name,
                    'study' => $study->study->name,
                    'protocol_id' => $version->protocol_id,
                    'protocol' => $version->protocol->name,
                    'protocol_version' => $version->version,
                    'is_reference_template' => $version->is_reference_template,
                    'media' => $version->media->map(fn (ProtocolVersionMedium $m) => [
                        'code' => $m->medium->code,
                        'name' => $m->medium->name,
                        'inoculum' => $m->inoculum,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{ready: int, in_progress: int, urgent: int}
     */
    public function counters(int $siteId): array
    {
        $rows = $this->open($siteId)
            ->selectRaw('status, priority, COUNT(*) AS total')
            ->groupBy('status', 'priority')
            ->toBase()
            ->get();

        $counters = ['ready' => 0, 'in_progress' => 0, 'urgent' => 0];
        foreach ($rows as $row) {
            $total = (int) $row->total;
            $counters[$row->status === ActivityStatus::InProgress->value ? 'in_progress' : 'ready'] += $total;
            if ($row->priority !== 'routine') {
                $counters['urgent'] += $total;
            }
        }

        return $counters;
    }

    /**
     * @return Builder<Activity>
     */
    private function open(int $siteId): Builder
    {
        return Activity::query()
            ->where('site_id', $siteId)
            ->where('type', ActivityType::Inoculate->value)
            ->whereIn('status', [ActivityStatus::Ready->value, ActivityStatus::InProgress->value]);
    }
}
