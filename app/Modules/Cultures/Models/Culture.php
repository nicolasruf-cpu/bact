<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $sample_study_id
 * @property int $attempt
 * @property CultureStatus $status
 * @property Carbon $inoculated_at
 * @property int $inoculated_by
 * @property string|null $notes
 * @property int $lock_version
 * @property-read SampleStudy $sampleStudy
 * @property-read Collection<int, Plate> $plates
 */
class Culture extends Model
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
            'status' => CultureStatus::class,
            'inoculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SampleStudy, $this>
     */
    public function sampleStudy(): BelongsTo
    {
        return $this->belongsTo(SampleStudy::class);
    }

    /**
     * @return HasMany<Plate, $this>
     */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }
}
