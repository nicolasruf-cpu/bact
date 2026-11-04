<?php

namespace App\Modules\Susceptibility\Data;

use App\Modules\Configuration\Enums\AstMethod;

/** Antibiótico que se prueba por fuera del panel (D-0022). */
final readonly class AdditionalAntibiotic
{
    public function __construct(
        public int $antibioticId,
        public AstMethod $method,
        public ?string $reason = null,
    ) {}
}
