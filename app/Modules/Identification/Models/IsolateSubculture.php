<?php

namespace App\Modules\Identification\Models;

use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identification\Enums\SubcultureResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repique o purificación de un aislamiento (revisión de marchas B4): la placa nueva y,
 * al leerla, si quedó puro.
 *
 * @property int $id
 * @property int $isolate_id
 * @property int $plate_id
 * @property string|null $reason
 * @property int $subcultured_by
 * @property CarbonImmutable $subcultured_at
 * @property SubcultureResult|null $result
 * @property string|null $notes
 * @property int|null $read_by
 * @property CarbonImmutable|null $read_at
 * @property-read Plate $plate
 * @property-read Isolate $isolate
 */
class IsolateSubculture extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'result' => SubcultureResult::class,
            'subcultured_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Plate, $this>
     */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }
}
