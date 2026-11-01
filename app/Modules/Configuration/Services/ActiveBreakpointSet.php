<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\BreakpointSet;

/**
 * Set de puntos de corte vigente. Se prefiere uno real; en producción el set ficticio
 * de demostración nunca se usa (Q-C4, MICROBIOLOGY_RULES §3).
 */
class ActiveBreakpointSet
{
    public function resolve(): ?BreakpointSet
    {
        return BreakpointSet::query()
            ->where('status', ProtocolVersionStatus::Active->value)
            ->when(app()->isProduction(), fn ($q) => $q->where('is_synthetic', false))
            ->orderBy('is_synthetic')
            ->latest('id')
            ->first();
    }
}
