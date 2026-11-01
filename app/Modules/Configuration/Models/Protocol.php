<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Protocolo = estudio + tipo de muestra. Su contenido clínico vive en versiones.
 *
 * @property int $id
 * @property int $study_id
 * @property int $sample_type_id
 * @property string $name
 * @property-read Study $study
 * @property-read SampleType $sampleType
 * @property-read ProtocolVersion|null $activeVersion
 */
#[Fillable(['study_id', 'sample_type_id', 'name'])]
class Protocol extends Model
{
    /**
     * @return BelongsTo<Study, $this>
     */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /**
     * @return BelongsTo<SampleType, $this>
     */
    public function sampleType(): BelongsTo
    {
        return $this->belongsTo(SampleType::class);
    }

    /**
     * @return HasMany<ProtocolVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProtocolVersion::class);
    }

    /**
     * @return HasOne<ProtocolVersion, $this>
     */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(ProtocolVersion::class)->where('status', ProtocolVersionStatus::Active->value);
    }
}
