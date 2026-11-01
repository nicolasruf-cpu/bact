<?php

namespace App\Modules\Workflow\Models;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Samples\Models\Sample;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Actividad: la unidad de trabajo (WORKFLOW_ENGINE.md).
 *
 * @property int $id
 * @property string $ulid
 * @property ActivityType $type
 * @property string $subject_type
 * @property int $subject_id
 * @property int $sample_id
 * @property int $site_id
 * @property ActivityStatus $status
 * @property Priority $priority
 * @property Carbon $available_at
 * @property Carbon|null $due_at
 * @property int|null $caused_by_activity_id
 * @property int $attempt
 * @property int|null $claimed_by
 * @property Carbon|null $claimed_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property string|null $cancellation_reason
 * @property int $lock_version
 * @property-read Sample $sample
 */
class Activity extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'ulid'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'priority' => Priority::class,
            'available_at' => 'datetime',
            'due_at' => 'datetime',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Sample, $this>
     */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isFinal() && $this->due_at !== null && $this->due_at->isPast();
    }
}
