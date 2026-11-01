<?php

namespace App\Modules\CriticalResults\Models;

use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Resultado crítico a comunicar. Queda abierto (visible en la barra superior) hasta que
 * se registra una comunicación exitosa.
 *
 * @property int $id
 * @property string $ulid
 * @property int $sample_study_id
 * @property int $site_id
 * @property CriticalAlertSource $source
 * @property string $source_key
 * @property string $summary
 * @property CriticalAlertStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int $lock_version
 * @property-read SampleStudy $sampleStudy
 * @property-read Collection<int, CriticalCommunication> $communications
 */
class CriticalAlert extends Model
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
            'source' => CriticalAlertSource::class,
            'status' => CriticalAlertStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return HasMany<CriticalCommunication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(CriticalCommunication::class)->orderBy('communicated_at');
    }
}
