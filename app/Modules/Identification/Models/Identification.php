<?php

namespace App\Modules\Identification\Models;

use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intento de identificación de un aislamiento (puede haber varios: preliminar, definitiva).
 *
 * @property int $id
 * @property int $isolate_id
 * @property IdentificationMethod $method
 * @property int|null $organism_id
 * @property int|null $instrument_run_id
 * @property IdentificationLevel $level
 * @property string|null $confidence
 * @property string|null $tests
 * @property string|null $notes
 * @property int $identified_by
 * @property Carbon $identified_at
 * @property-read Organism|null $organism
 * @property-read InstrumentRun|null $instrumentRun
 * @property-read Isolate $isolate
 */
class Identification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method' => IdentificationMethod::class,
            'level' => IdentificationLevel::class,
            'identified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organism, $this>
     */
    public function organism(): BelongsTo
    {
        return $this->belongsTo(Organism::class);
    }

    /**
     * @return BelongsTo<InstrumentRun, $this>
     */
    public function instrumentRun(): BelongsTo
    {
        return $this->belongsTo(InstrumentRun::class);
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }
}
