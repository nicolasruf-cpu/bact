<?php

namespace App\Modules\Susceptibility\Events;

use App\Modules\Susceptibility\Models\SusceptibilityTest;

final readonly class SusceptibilityTestRead
{
    public function __construct(
        public SusceptibilityTest $test,
        public int $userId,
    ) {}
}
