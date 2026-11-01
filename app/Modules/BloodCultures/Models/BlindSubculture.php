<?php

namespace App\Modules\BloodCultures\Models;

use App\Modules\BloodCultures\Enums\BlindSubcultureResult;
use App\Modules\Configuration\Models\Medium;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Repique del frasco a una placa (procesamiento manual). La placa se lee después.
 *
 * @property int $id
 * @property string $ulid
 * @property int $blood_culture_bottle_id
 * @property int $number
 * @property int $medium_id
 * @property Carbon $subcultured_at
 * @property int $subcultured_by
 * @property BlindSubcultureResult|null $result
 * @property string|null $notes
 * @property Carbon|null $read_at
 * @property int|null $read_by
 * @property-read BloodCultureBottle $bottle
 * @property-read Medium $medium
 */
class BlindSubculture extends Model
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
            'result' => BlindSubcultureResult::class,
            'subcultured_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BloodCultureBottle, $this>
     */
    public function bottle(): BelongsTo
    {
        return $this->belongsTo(BloodCultureBottle::class, 'blood_culture_bottle_id');
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }
}
