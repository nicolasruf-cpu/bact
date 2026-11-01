<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conjunto de puntos de corte (una edición del M100 cargada por el laboratorio).
 * `is_synthetic` marca el set FICTICIO de demostración: nunca en producción.
 *
 * @property int $id
 * @property string $name
 * @property string $edition
 * @property ProtocolVersionStatus $status
 * @property bool $is_synthetic
 * @property-read Collection<int, Breakpoint> $breakpoints
 */
class BreakpointSet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProtocolVersionStatus::class,
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Breakpoint, $this>
     */
    public function breakpoints(): HasMany
    {
        return $this->hasMany(Breakpoint::class);
    }
}
