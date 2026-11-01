<?php

namespace App\Modules\BloodCultures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gram del frasco positivo: una opción del protocolo y una descripción libre.
 *
 * @property int $id
 * @property string $ulid
 * @property int $blood_culture_bottle_id
 * @property string $option_code
 * @property string|null $description
 * @property int $performed_by
 * @property Carbon $performed_at
 * @property-read BloodCultureBottle $bottle
 */
class GramStain extends Model
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

    protected function casts(): array
    {
        return ['performed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<BloodCultureBottle, $this>
     */
    public function bottle(): BelongsTo
    {
        return $this->belongsTo(BloodCultureBottle::class, 'blood_culture_bottle_id');
    }
}
