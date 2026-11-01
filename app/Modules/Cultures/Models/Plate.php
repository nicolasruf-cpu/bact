<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Cultures\Enums\PlateStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $culture_id
 * @property int|null $source_plate_id
 * @property int $medium_id
 * @property string $inoculum
 * @property string $incubation_temperature
 * @property Atmosphere $atmosphere
 * @property bool $is_enrichment
 * @property Carbon|null $inoculated_at
 * @property PlateStatus $status
 * @property string|null $lost_reason
 * @property-read Medium $medium
 * @property-read Culture $culture
 * @property-read Collection<int, Plate> $subcultures
 */
class Plate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'atmosphere' => Atmosphere::class,
            'status' => PlateStatus::class,
            'is_enrichment' => 'boolean',
            'inoculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }

    /**
     * @return BelongsTo<Culture, $this>
     */
    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class);
    }

    /**
     * Placas sembradas desde este caldo de enriquecimiento.
     *
     * @return HasMany<Plate, $this>
     */
    public function subcultures(): HasMany
    {
        return $this->hasMany(Plate::class, 'source_plate_id');
    }
}
