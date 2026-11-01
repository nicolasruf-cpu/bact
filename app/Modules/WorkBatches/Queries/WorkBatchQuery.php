<?php

namespace App\Modules\WorkBatches\Queries;

use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Enums\WorkBatchStatus;
use App\Modules\WorkBatches\Enums\WorkBatchType;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\WorkBatches\Models\WorkBatchItem;
use App\Modules\Workflow\Queries\InoculationWorkbenchQuery;

class WorkBatchQuery
{
    public function __construct(private readonly InoculationWorkbenchQuery $inoculation) {}

    /**
     * @return array<string, mixed>
     */
    public function show(WorkBatch $batch): array
    {
        $batch->load(['items', 'owner:id,name']);
        $details = match ($batch->type) {
            WorkBatchType::Inoculation => $this->inoculation->forActivities(array_values(array_map('intval', $batch->items->pluck('activity_id')->all()))),
            WorkBatchType::Reading => [],
        };

        return [
            'ulid' => $batch->ulid,
            'code' => $batch->code,
            'type' => $batch->type->value,
            'type_label' => $batch->type->label(),
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'owner' => $batch->owner->name,
            'owner_id' => $batch->owner_id,
            'started_at' => $batch->started_at?->toIso8601String(),
            'items' => $batch->items->map(fn (WorkBatchItem $item) => [
                'id' => $item->id,
                'position' => $item->position,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'removed_reason' => $item->removed_reason,
                'activity' => $details[$item->activity_id] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * Tandas abiertas (en proceso o en pausa) de un usuario en la sede.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openFor(int $userId, int $siteId): array
    {
        return WorkBatch::query()
            ->where('owner_id', $userId)
            ->where('site_id', $siteId)
            ->whereIn('status', [WorkBatchStatus::InProgress->value, WorkBatchStatus::Paused->value])
            ->withCount([
                'items',
                'items as done_count' => fn ($q) => $q->where('status', WorkBatchItemStatus::Done->value),
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (WorkBatch $batch) => [
                'ulid' => $batch->ulid,
                'code' => $batch->code,
                'type_label' => $batch->type->label(),
                'status' => $batch->status->value,
                'status_label' => $batch->status->label(),
                'items' => (int) $batch->getAttribute('items_count'),
                'done' => (int) $batch->getAttribute('done_count'),
            ])
            ->values()
            ->all();
    }
}
