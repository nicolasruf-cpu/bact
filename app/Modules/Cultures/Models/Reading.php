<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Configuration\Enums\ReadingOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Lectura de un cultivo. Inmutable: una corrección es una lectura nueva.
 *
 * @property int $id
 * @property int $culture_id
 * @property int $attempt
 * @property string $option_code
 * @property ReadingOutcome $outcome
 * @property string|null $notes
 * @property int $read_by
 * @property Carbon $read_at
 * @property-read Culture $culture
 */
class Reading extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'outcome' => ReadingOutcome::class,
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las lecturas no se modifican: se registra una nueva.'));
    }

    /**
     * @return BelongsTo<Culture, $this>
     */
    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class);
    }
}
