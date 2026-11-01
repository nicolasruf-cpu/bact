<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conjunto de puntos de corte (una edición del M100 cargada por el laboratorio).
 * `is_synthetic` marca el set FICTICIO de demostración: nunca en producción.
 *
 * @property int $id
 * @property string $name
 * @property string $edition
 * @property ProtocolVersionStatus $status
 * @property bool $is_synthetic
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $retired_at
 * @property int $lock_version
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
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
            'lock_version' => 'integer',
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
