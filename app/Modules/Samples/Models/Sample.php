<?php

namespace App\Modules\Samples\Models;

use App\Modules\Configuration\Models\SampleType;
use App\Modules\Orders\Models\LabOrder;
use App\Modules\Organization\Models\Site;
use App\Modules\Samples\Enums\SampleStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Muestra: la unidad de seguimiento.
 *
 * @property int $id
 * @property string $ulid
 * @property string $barcode
 * @property int $lab_order_id
 * @property int $reception_id
 * @property int $origin_site_id
 * @property int $sample_type_id
 * @property SampleStatus $status
 * @property Carbon|null $collected_at
 * @property Carbon $received_at
 * @property int $received_by
 * @property string|null $reception_notes
 * @property int $lock_version
 * @property-read LabOrder $labOrder
 * @property-read Reception $reception
 * @property-read SampleType $sampleType
 * @property-read Site $originSite
 * @property-read Collection<int, SampleStudy> $studies
 */
class Sample extends Model
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
            'status' => SampleStatus::class,
            'collected_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LabOrder, $this>
     */
    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    /**
     * @return BelongsTo<Reception, $this>
     */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    /**
     * @return BelongsTo<SampleType, $this>
     */
    public function sampleType(): BelongsTo
    {
        return $this->belongsTo(SampleType::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function originSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'origin_site_id');
    }

    /**
     * @return HasMany<SampleStudy, $this>
     */
    public function studies(): HasMany
    {
        return $this->hasMany(SampleStudy::class);
    }
}
