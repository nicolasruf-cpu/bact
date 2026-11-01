<?php

namespace App\Modules\WorkBatches\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\WorkBatches\Enums\WorkBatchAuditAction;
use App\Modules\WorkBatches\Enums\WorkBatchEventType;
use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Models\WorkBatchItem;
use App\Modules\WorkBatches\Services\BatchJournal;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Services\ActivityLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retira un ítem pendiente de la tanda (con motivo): la actividad vuelve a la mesada.
 */
class RemoveBatchItem
{
    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly BatchJournal $journal,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, WorkBatchItem $item, string $reason): void
    {
        DB::transaction(function () use ($user, $item, $reason) {
            /** @var WorkBatchItem $item */
            $item = WorkBatchItem::query()->lockForUpdate()->with(['activity', 'batch'])->findOrFail($item->id);

            if ($item->status !== WorkBatchItemStatus::Pending) {
                throw ValidationException::withMessages(['item' => 'Solo se pueden retirar ítems pendientes.']);
            }

            if ($item->activity->status === ActivityStatus::InProgress) {
                $this->lifecycle->release($item->activity);
            }
            $item->update(['status' => WorkBatchItemStatus::Removed, 'removed_reason' => $reason]);

            $this->journal->record($item->batch, WorkBatchEventType::ItemRemoved, $user->id, ['activity_id' => $item->activity_id, 'reason' => $reason]);
            $this->auditor->record(WorkBatchAuditAction::ItemRemoved, $item->batch, after: ['activity_id' => $item->activity_id], reason: $reason);
        });
    }
}
