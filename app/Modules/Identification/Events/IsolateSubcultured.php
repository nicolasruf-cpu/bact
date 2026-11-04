<?php

namespace App\Modules\Identification\Events;

use App\Modules\Identification\Models\IsolateSubculture;
use Carbon\CarbonInterface;

/** Aislamiento repicado: Workflow programa la lectura del repique. */
final readonly class IsolateSubcultured
{
    public function __construct(
        public IsolateSubculture $subculture,
        public CarbonInterface $readableAt,
        public int $userId,
    ) {}
}
