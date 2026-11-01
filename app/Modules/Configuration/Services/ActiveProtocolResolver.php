<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\ProtocolVersion;

class ActiveProtocolResolver
{
    /**
     * Versión vigente del protocolo para un estudio y tipo de muestra, o null si no hay.
     */
    public function resolve(int $studyId, int $sampleTypeId): ?ProtocolVersion
    {
        return ProtocolVersion::query()
            ->where('status', ProtocolVersionStatus::Active->value)
            ->whereHas('protocol', fn ($query) => $query
                ->where('study_id', $studyId)
                ->where('sample_type_id', $sampleTypeId))
            ->with('media.medium')
            ->first();
    }
}
