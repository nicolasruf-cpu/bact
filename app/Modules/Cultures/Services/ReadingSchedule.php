<?php

namespace App\Modules\Cultures\Services;

use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Reading;
use Carbon\CarbonInterface;

/**
 * Desde cuándo se puede leer un cultivo, según la versión de protocolo congelada:
 * 1.ª lectura = siembra + incubación mínima; después de reincubar = última lectura + reincubación.
 */
class ReadingSchedule
{
    public function nextReadingAvailableAt(Culture $culture): CarbonInterface
    {
        $version = $culture->sampleStudy->protocolVersion;
        /** @var Reading|null $last */
        $last = $culture->readings->last();

        if ($last !== null && $last->outcome === ReadingOutcome::Reincubate) {
            return $last->read_at->copy()->addHours((int) $version->reincubation_hours);
        }

        return $culture->inoculated_at->copy()->addHours($version->first_read_min_hours);
    }
}
