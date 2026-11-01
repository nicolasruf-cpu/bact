<?php

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud ("Orders" para no chocar con Request de HTTP).
 *
 * @property int $id
 * @property string $ulid
 * @property int $site_id
 * @property int $patient_id
 * @property string|null $external_number
 * @property string|null $requesting_service
 * @property string|null $requesting_physician
 * @property Priority $priority
 * @property int $created_by
 * @property-read Patient $patient
 */
class LabOrder extends Model
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
        return ['priority' => Priority::class];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
