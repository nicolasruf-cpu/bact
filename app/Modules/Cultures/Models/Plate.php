<?php

namespace App\Modules\Cultures\Models;

use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Cultures\Enums\PlateStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $culture_id
 * @property int|null $source_plate_id
 * @property int|null $replaces_plate_id resiembra de una placa perdida
 * @property int|null $source_isolate_id repique de un aislamiento (no se lee con el cultivo)
 * @property int|null $read_from_hours
 * @property int|null $read_until_hours
 * @property int $medium_id
 * @property string $inoculum
 * @property string $incubation_temperature
 * @property Atmosphere $atmosphere
 * @property bool $is_enrichment
 * @property Carbon|null $inoculated_at
 * @property PlateStatus $status
 * @property string|null $lost_reason
 * @property Carbon|null $lost_at
 * @property int|null $lost_by
 * @property-read Medium $medium
 * @property-read Culture $culture
 * @property-read Collection<int, Plate> $subcultures
 */
class Plate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'atmosphere' => Atmosphere::class,
            'status' => PlateStatus::class,
            'is_enrichment' => 'boolean',
            'inoculated_at' => 'datetime',
            'lost_at' => 'datetime',
            'read_from_hours' => 'integer',
            'read_until_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }

    /**
     * @return BelongsTo<Culture, $this>
     */
    public function culture(): BelongsTo
    {
        return $this->belongsTo(Culture::class);
    }

    /**
     * Placa que se lee con el cultivo: no es un caldo, no se perdió y no es el repique de un aislamiento.
     */
    public function isRead(): bool
    {
        return ! $this->is_enrichment && $this->status !== PlateStatus::Lost && $this->source_isolate_id === null;
    }

    /**
     * La placa se lee en la lectura programada de esas horas (A3): cada medio puede leerse
     * solo en una parte del cronograma (p. ej., Campylobacter desde las 48 h).
     */
    public function isReadAt(int $stepHours): bool
    {
        return $this->isRead()
            && ($this->read_from_hours === null || $stepHours >= $this->read_from_hours)
            && ($this->read_until_hours === null || $stepHours <= $this->read_until_hours);
    }

    /**
     * Placas sembradas desde este caldo de enriquecimiento.
     *
     * @return HasMany<Plate, $this>
     */
    public function subcultures(): HasMany
    {
        return $this->hasMany(Plate::class, 'source_plate_id');
    }
}
