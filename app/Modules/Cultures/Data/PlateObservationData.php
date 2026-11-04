<?php

namespace App\Modules\Cultures\Data;

use App\Modules\Cultures\Enums\PlateObservation;

final readonly class PlateObservationData
{
    public function __construct(
        public int $plateId,
        public PlateObservation $observation,
        public ?string $notes = null,
    ) {}
}
