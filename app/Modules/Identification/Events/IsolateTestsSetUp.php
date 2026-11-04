<?php

namespace App\Modules\Identification\Events;

use App\Modules\Cultures\Models\Isolate;
use Carbon\CarbonInterface;

/** Pruebas sembradas sin leer: Workflow programa su lectura. */
final readonly class IsolateTestsSetUp
{
    public function __construct(
        public Isolate $isolate,
        public CarbonInterface $readableAt,
        public int $userId,
    ) {}
}
