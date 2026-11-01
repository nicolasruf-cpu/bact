<?php

namespace App\Modules\Susceptibility\Events;

use App\Modules\Susceptibility\Models\SusceptibilityTest;

final readonly class SusceptibilityTestSetUp
{
    public function __construct(
        public SusceptibilityTest $test,
        public int $userId,
    ) {}
}
