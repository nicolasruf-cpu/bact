<?php

namespace App\Modules\Susceptibility\Models;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Models\Antibiotic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Resultado por antibiótico: valor medido → interpretación automática → interpretación
 * revisada (con motivo) → se informa o no (MICROBIOLOGY_RULES §3). Cada antibiótico tiene su
 * método y su lectura: los adicionales, por fuera del panel, pueden leerse después (D-0022).
 *
 * @property int $id
 * @property int $susceptibility_test_id
 * @property int $antibiotic_id
 * @property AstMethod $method
 * @property bool $is_additional
 * @property string|null $addition_reason
 * @property Carbon $set_up_at
 * @property int|null $set_up_by
 * @property Carbon|null $read_at
 * @property string|null $mic_operator
 * @property string|null $measured_value
 * @property Interpretation|null $automatic_interpretation
 * @property Interpretation|null $reviewed_interpretation
 * @property string|null $override_reason
 * @property string|null $rule_notes reglas de informe o expertas aplicadas al leer (C2)
 * @property bool $is_reported
 * @property-read Antibiotic $antibiotic
 */
class SusceptibilityResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method' => AstMethod::class,
            'is_additional' => 'boolean',
            'set_up_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
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

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Lo que se informa: la revisada si existe, si no la automática.
     */
    public function finalInterpretation(): ?Interpretation
    {
        return $this->reviewed_interpretation ?? $this->automatic_interpretation;
    }
}
