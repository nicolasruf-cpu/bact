<?php

namespace App\Modules\WorkBatches\Models;

use App\Modules\WorkBatches\Enums\WorkBatchItemStatus;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $work_batch_id
 * @property int $activity_id
 * @property int $position
 * @property WorkBatchItemStatus $status
 * @property string|null $removed_reason
 * @property Carbon|null $done_at
 * @property-read Activity $activity
 * @property-read WorkBatch $batch
 */
class WorkBatchItem extends Model
{
    protected $guarded = ['id', 'active_activity_id'];

    protected function casts(): array
    {
        return [
            'status' => WorkBatchItemStatus::class,
            'done_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<WorkBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(WorkBatch::class, 'work_batch_id');
    }
}
