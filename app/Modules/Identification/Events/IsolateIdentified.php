<?php

namespace App\Modules\Identification\Events;

use App\Modules\Cultures\Models\Isolate;

/**
 * Identificación definitiva registrada. Si requiere antibiograma, Workflow crea su preparación.
 */
final readonly class IsolateIdentified
{
    public function __construct(
        public Isolate $isolate,
        public bool $requiresSusceptibility,
        public int $userId,
    ) {}
}
