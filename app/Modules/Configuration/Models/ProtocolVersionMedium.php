<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\Atmosphere;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medio a sembrar en una versión de protocolo, con su inóculo e incubación.
 * Un caldo de enriquecimiento no se lee: se repica a placas a las horas indicadas.
 *
 * @property int $id
 * @property int $protocol_version_id
 * @property int $medium_id
 * @property int $position
 * @property string $inoculum
 * @property string $incubation_temperature
 * @property Atmosphere $atmosphere
 * @property bool $is_enrichment
 * @property int|null $enrichment_subculture_min_hours
 * @property int|null $enrichment_subculture_due_hours
 * @property list<array{medium_code: string, incubation_temperature: string, atmosphere: string}>|null $enrichment_subculture_media
 * @property int|null $read_from_hours lecturas del cronograma desde esta hora (null = desde la primera)
 * @property int|null $read_until_hours lecturas del cronograma hasta esta hora (null = hasta la última)
 * @property list<string>|null $bottle_types en hemocultivo, solo para estos tipos de frasco (null = todos)
 * @property-read Medium $medium
 */
class ProtocolVersionMedium extends Model
{
    protected $table = 'protocol_version_media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'atmosphere' => Atmosphere::class,
            'is_enrichment' => 'boolean',
            'enrichment_subculture_media' => 'array',
            'read_from_hours' => 'integer',
            'read_until_hours' => 'integer',
            'bottle_types' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Medium, $this>
     */
    public function medium(): BelongsTo
    {
        return $this->belongsTo(Medium::class);
    }
}
