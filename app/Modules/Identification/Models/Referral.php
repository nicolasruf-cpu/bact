<?php

namespace App\Modules\Identification\Models;

use App\Modules\Cultures\Models\Isolate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derivación de un aislamiento a otro laboratorio (revisión de marchas B9), p. ej. para
 * serogrupo o confirmación de un mecanismo, y el resultado que informan.
 *
 * @property int $id
 * @property int $isolate_id
 * @property string $destination
 * @property string $reason
 * @property int $sent_by
 * @property CarbonImmutable $sent_at
 * @property string|null $result
 * @property int|null $result_recorded_by
 * @property CarbonImmutable|null $result_at
 * @property-read Isolate $isolate
 */
class Referral extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'result_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }
}
