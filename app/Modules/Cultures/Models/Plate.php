<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Cultures\Enums\PlateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $culture_id
 * @property int $medium_id
 * @property string $inoculum
 * @property string $incubation_temperature
 * @property Atmosphere $atmosphere
 * @property PlateStatus $status
 * @property string|null $lost_reason
 * @property-read Medium $medium
 * @property-read Culture $culture
 */
class Plate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'atmosphere' => Atmosphere::class,
            'status' => PlateStatus::class,
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
}
