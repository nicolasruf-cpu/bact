<?php

namespace App\Modules\Susceptibility\Data;

use App\Modules\Configuration\Enums\Interpretation;

final readonly class ResultInput
{
    public function __construct(
        public int $antibioticId,
        public ?string $micOperator,
        public ?float $value,
        public ?Interpretation $reviewed,
        public ?string $overrideReason,
        public bool $reported,
    ) {}
}
