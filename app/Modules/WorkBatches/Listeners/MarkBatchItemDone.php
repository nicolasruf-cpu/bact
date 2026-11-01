<?php

namespace App\Modules\WorkBatches\Listeners;

use App\Modules\WorkBatches\Enums\WorkBatchEventType;
use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Models\WorkBatchItem;
use App\Modules\WorkBatches\Services\BatchJournal;
use App\Modules\Workflow\Events\ActivityCompleted;
use Illuminate\Support\Facades\Date;

/**
 * Cuando una actividad se completa (en la misma transacción), su ítem de tanda queda hecho.
 */
class MarkBatchItemDone
{
    public function __construct(private readonly BatchJournal $journal) {}

    public function handle(ActivityCompleted $event): void
    {
        $item = WorkBatchItem::query()
            ->where('activity_id', $event->activity->id)
            ->where('status', WorkBatchItemStatus::Pending->value)
            ->with('batch')
            ->first();

        if ($item === null) {
            return;
        }

        $item->update(['status' => WorkBatchItemStatus::Done, 'done_at' => Date::now()]);
        $this->journal->record($item->batch, WorkBatchEventType::ItemDone, (int) $event->activity->completed_by, ['activity_id' => $item->activity_id]);
    }
}
