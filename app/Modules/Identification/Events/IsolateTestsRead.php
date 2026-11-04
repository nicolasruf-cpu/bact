<?php

namespace App\Modules\Identification\Events;

use App\Modules\Cultures\Models\Isolate;
use Carbon\CarbonInterface;

/** Pruebas leídas. Si quedan otras sin leer, desde cuándo se pueden leer. */
final readonly class IsolateTestsRead
{
    public function __construct(
        public Isolate $isolate,
        public ?CarbonInterface $nextReadableAt,
        public int $userId,
    ) {}
}
