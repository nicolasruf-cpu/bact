<?php

namespace App\Modules\Identification\Models;

use App\Modules\Cultures\Models\Isolate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carga de un aislamiento en un equipo (VITEK, revisión de marchas B3): las tarjetas de
 * identificación y de antibiograma, que se cargan juntas con la misma suspensión, el casete
 * y la posición, y cuándo dio cada resultado.
 *
 * @property int $id
 * @property int $isolate_id
 * @property string $instrument
 * @property string|null $id_card_type
 * @property string|null $id_card_number
 * @property string|null $ast_card_type
 * @property string|null $ast_card_number
 * @property string|null $cassette
 * @property string|null $position
 * @property string|null $suspension
 * @property int $loaded_by
 * @property CarbonImmutable $loaded_at
 * @property CarbonImmutable|null $id_result_at
 * @property CarbonImmutable|null $ast_result_at
 * @property-read Isolate $isolate
 */
class InstrumentRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'immutable_datetime',
            'id_result_at' => 'immutable_datetime',
            'ast_result_at' => 'immutable_datetime',
        ];
    }

    public function hasIdCard(): bool
    {
        return $this->id_card_type !== null;
    }

    public function hasAstCard(): bool
    {
        return $this->ast_card_type !== null;
    }

    public function isPending(): bool
    {
        return ($this->hasIdCard() && $this->id_result_at === null) || ($this->hasAstCard() && $this->ast_result_at === null);
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }
}
