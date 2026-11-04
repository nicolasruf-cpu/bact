<?php

namespace App\Modules\Identification\Events;

use App\Modules\Cultures\Models\Isolate;

/**
 * Cambió la significancia. Workflow cancela lo pendiente del aislamiento que ya no
 * corresponde y, si vuelve a trabajarse, crea la identificación o la preparación del
 * antibiograma (`reopen`: 'identify' | 'ast_setup').
 */
final readonly class IsolateSignificanceChanged
{
    public function __construct(
        public Isolate $isolate,
        public ?string $reopen,
        public int $userId,
    ) {}
}
