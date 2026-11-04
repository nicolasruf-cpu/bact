<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\IsolateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aislamiento de un cultivo. La significancia la marca el bacteriólogo y la puede cambiar
 * con motivo (revisión de marchas A4); `is_significant` acompaña a `significance`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $culture_id
 * @property int $number
 * @property string $morphotype
 * @property string|null $count_category
 * @property int|null $plate_id
 * @property bool $is_significant
 * @property IsolateSignificance $significance
 * @property string|null $significance_reason
 * @property IsolateStatus $status
 * @property int|null $organism_id
 * @property string|null $no_ast_reason
 * @property int $lock_version
 * @property-read Culture $culture
 * @property-read Organism|null $organism
 * @property-read Plate|null $plate
 */
class Isolate extends Model
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

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'is_significant' => 'boolean',
            'significance' => IsolateSignificance::class,
            'status' => IsolateStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Culture, $this>
     */
    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class);
    }

    /**
     * @return BelongsTo<Organism, $this>
     */
    public function organism(): BelongsTo
    {
        return $this->belongsTo(Organism::class);
    }

    /**
     * @return BelongsTo<Plate, $this>
     */
    public function plate(): BelongsTo
    {
        return $this->belongsTo(Plate::class);
    }
}
