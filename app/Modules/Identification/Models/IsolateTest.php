<?php

namespace App\Modules\Identification\Models;

use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Cultures\Models\Isolate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una prueba hecha sobre un aislamiento: cuándo se sembró, desde cuándo se puede leer y
 * qué dio. El resultado guarda también el texto de la opción, para que el historial no
 * cambie si después se edita el catálogo.
 *
 * @property int $id
 * @property int $isolate_id
 * @property int $identification_test_id
 * @property int|null $susceptibility_test_id
 * @property CarbonImmutable $set_up_at
 * @property int $set_up_by
 * @property CarbonImmutable $readable_at
 * @property string|null $result_code
 * @property string|null $result_label
 * @property string|null $notes
 * @property CarbonImmutable|null $read_at
 * @property int|null $read_by
 * @property-read IdentificationTest $test
 * @property-read Isolate $isolate
 */
class IsolateTest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'set_up_at' => 'immutable_datetime',
            'readable_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * @return BelongsTo<IdentificationTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(IdentificationTest::class, 'identification_test_id');
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }
}
