<?php

namespace App\Modules\Reporting\Models;

use App\Models\User;
use App\Modules\Reporting\Enums\ReportStatus;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Informe validado. Su contenido (snapshot) es inmutable: corregir = nueva versión.
 *
 * @property int $id
 * @property string $ulid
 * @property int $sample_study_id
 * @property int $version
 * @property ReportType $type
 * @property ReportStatus $status
 * @property array<string, mixed> $snapshot
 * @property int $validated_by
 * @property Carbon $validated_at
 * @property-read SampleStudy $sampleStudy
 * @property-read User $validator
 */
class Report extends Model
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
            'type' => ReportType::class,
            'status' => ReportStatus::class,
            'snapshot' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Report $report) {
            if ($report->isDirty('snapshot')) {
                throw new LogicException('El contenido de un informe emitido no se modifica: se emite una nueva versión.');
            }
        });
    }

    /**
     * @return BelongsTo<SampleStudy, $this>
     */
    public function sampleStudy(): BelongsTo
    {
        return $this->belongsTo(SampleStudy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
