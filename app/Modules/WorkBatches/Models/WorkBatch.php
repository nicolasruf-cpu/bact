<?php

namespace App\Modules\WorkBatches\Models;

use App\Models\User;
use App\Modules\WorkBatches\Enums\WorkBatchStatus;
use App\Modules\WorkBatches\Enums\WorkBatchType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property WorkBatchType $type
 * @property int $site_id
 * @property int $owner_id
 * @property WorkBatchStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $completed_at
 * @property int $lock_version
 * @property-read User $owner
 * @property-read Collection<int, WorkBatchItem> $items
 */
class WorkBatch extends Model
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
            'type' => WorkBatchType::class,
            'status' => WorkBatchStatus::class,
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkBatchItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkBatchItem::class)->orderBy('position');
    }
}
