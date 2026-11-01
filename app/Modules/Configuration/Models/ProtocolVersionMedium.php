<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\Atmosphere;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medio a sembrar en una versión de protocolo, con su inóculo e incubación.
 *
 * @property int $id
 * @property int $protocol_version_id
 * @property int $medium_id
 * @property int $position
 * @property string $inoculum
 * @property string $incubation_temperature
 * @property Atmosphere $atmosphere
 * @property-read Medium $medium
 */
class ProtocolVersionMedium extends Model
{
    protected $table = 'protocol_version_media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['atmosphere' => Atmosphere::class];
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }
}
