<?php

namespace App\Modules\Samples\Models;

use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\Study;
use App\Modules\Samples\Enums\SampleStudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estudio solicitado sobre una muestra, con la versión de protocolo congelada.
 *
 * @property int $id
 * @property int $sample_id
 * @property int $study_id
 * @property int $protocol_version_id
 * @property int $processing_site_id
 * @property SampleStudyStatus $status
 * @property int $lock_version
 * @property-read Sample $sample
 * @property-read Study $study
 * @property-read ProtocolVersion $protocolVersion
 */
class SampleStudy extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => SampleStudyStatus::class];
    }

    /**
     * @return BelongsTo<Sample, $this>
     */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /**
     * @return BelongsTo<Study, $this>
     */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /**
     * @return BelongsTo<ProtocolVersion, $this>
     */
    public function protocolVersion(): BelongsTo
    {
        return $this->belongsTo(ProtocolVersion::class);
    }
}
