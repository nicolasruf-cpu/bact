<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Cultures\Enums\PlateObservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Lo que se vio en una placa en una lectura. Inmutable, como la lectura.
 *
 * @property int $id
 * @property int $reading_id
 * @property int $plate_id
 * @property PlateObservation $observation
 * @property string|null $notes
 * @property-read Plate $plate
 * @property-read Reading $reading
 */
class PlateReading extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['observation' => PlateObservation::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las lecturas no se modifican: se registra una nueva.'));
    }

    /**
     * @return BelongsTo<Plate, $this>
     */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }

    /**
     * @return BelongsTo<Reading, $this>
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(Reading::class);
    }
}
