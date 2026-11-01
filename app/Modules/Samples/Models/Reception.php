<?php

namespace App\Modules\Samples\Models;

use App\Modules\Orders\Models\LabOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una entrega física: una o varias muestras de una orden, recibidas juntas.
 *
 * @property int $id
 * @property string $ulid
 * @property int $lab_order_id
 * @property int $site_id
 * @property Carbon $received_at
 * @property int $received_by
 * @property string $idempotency_key
 * @property-read LabOrder $labOrder
 * @property-read Collection<int, Sample> $samples
 */
class Reception extends Model
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

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<LabOrder, $this>
     */
    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    /**
     * @return HasMany<Sample, $this>
     */
    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }
}
