<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Lectura registrada. Workflow completa la lectura, programa la reincubación si corresponde
 * y crea la identificación de cada aislamiento significativo.
 */
final readonly class CultureRead
{
    /**
     * @param  Collection<int, Isolate>  $significantIsolates
     */
    public function __construct(
        public Culture $culture,
        public Reading $reading,
        public Collection $significantIsolates,
        public ?CarbonInterface $nextReadAt,
        public ?CarbonInterface $nextReadDueAt,
        public int $userId,
    ) {}
}
