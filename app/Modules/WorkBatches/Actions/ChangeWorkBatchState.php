<?php

namespace App\Modules\WorkBatches\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\WorkBatches\Enums\WorkBatchAuditAction;
use App\Modules\WorkBatches\Enums\WorkBatchEventType;
use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\WorkBatches\Enums\WorkBatchStatus;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\WorkBatches\Models\WorkBatchItem;
use App\Modules\WorkBatches\Services\BatchJournal;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Services\ActivityLifecycle;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pausar, reanudar o finalizar una tanda. Al finalizar, los ítems pendientes vuelven
 * a la mesada: nunca se pierden en silencio (WORK_BATCHES.md §2).
 */
class ChangeWorkBatchState
{
    public const PAUSE = 'pause';

    public const RESUME = 'resume';

    public const FINISH = 'finish';

    public function __construct(
        private readonly ActivityLifecycle $lifecycle,
        private readonly BatchJournal $journal,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  self::PAUSE|self::RESUME|self::FINISH  $operation
     *
     * @throws ValidationException
     */
    public function handle(User $user, WorkBatch $batch, string $operation, ?string $reason = null): WorkBatch
    {
        return DB::transaction(function () use ($user, $batch, $operation, $reason) {
            /** @var WorkBatch $batch */
            $batch = WorkBatch::query()->lockForUpdate()->findOrFail($batch->id);

            [$from, $to, $event, $audit] = match ($operation) {
                self::PAUSE => [[WorkBatchStatus::InProgress], WorkBatchStatus::Paused, WorkBatchEventType::Paused, WorkBatchAuditAction::Paused],
                self::RESUME => [[WorkBatchStatus::Paused], WorkBatchStatus::InProgress, WorkBatchEventType::Resumed, WorkBatchAuditAction::Resumed],
                self::FINISH => [[WorkBatchStatus::InProgress, WorkBatchStatus::Paused], WorkBatchStatus::Completed, WorkBatchEventType::Finished, WorkBatchAuditAction::Finished],
            };

            if (! in_array($batch->status, $from, true)) {
                throw ValidationException::withMessages(['batch' => "La tanda está {$batch->status->label()}."]);
            }

            $released = 0;
            if ($operation === self::FINISH) {
                $pending = $batch->items()->where('status', WorkBatchItemStatus::Pending->value)->with('activity')->get();
                foreach ($pending as $item) {
                    /** @var WorkBatchItem $item */
                    if ($item->activity->status === ActivityStatus::InProgress) {
                        $this->lifecycle->release($item->activity);
                    }
                    $item->update(['status' => WorkBatchItemStatus::Released]);
                    $released++;
                }
            }

            $batch->fill([
                'status' => $to,
                'paused_at' => $to === WorkBatchStatus::Paused ? Date::now() : null,
                'completed_at' => $to === WorkBatchStatus::Completed ? Date::now() : $batch->completed_at,
                'lock_version' => $batch->lock_version + 1,
            ])->save();

            $data = array_filter(['reason' => $reason, 'released' => $released ?: null]);
            $this->journal->record($batch, $event, $user->id, $data);
            $this->auditor->record($audit, $batch, after: ['status' => $to->value] + $data, reason: $reason);

            return $batch;
        });
    }
}
