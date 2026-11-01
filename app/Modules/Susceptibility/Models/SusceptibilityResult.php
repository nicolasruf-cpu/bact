<?php

namespace App\Modules\Susceptibility\Models;

use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Models\Antibiotic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado por antibiótico: valor medido → interpretación automática → interpretación
 * revisada (con motivo) → se informa o no (MICROBIOLOGY_RULES §3).
 *
 * @property int $id
 * @property int $susceptibility_test_id
 * @property int $antibiotic_id
 * @property string|null $mic_operator
 * @property string|null $measured_value
 * @property Interpretation|null $automatic_interpretation
 * @property Interpretation|null $reviewed_interpretation
 * @property string|null $override_reason
 * @property bool $is_reported
 * @property-read Antibiotic $antibiotic
 */
class SusceptibilityResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'automatic_interpretation' => Interpretation::class,
            'reviewed_interpretation' => Interpretation::class,
            'is_reported' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Antibiotic, $this>
     */
    public function antibiotic(): BelongsTo
    {
        return $this->belongsTo(Antibiotic::class);
    }

    /**
     * Lo que se informa: la revisada si existe, si no la automática.
     */
    public function finalInterpretation(): ?Interpretation
    {
        return $this->reviewed_interpretation ?? $this->automatic_interpretation;
    }
}
