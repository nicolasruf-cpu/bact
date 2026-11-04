<?php

namespace App\Modules\Susceptibility\Events;

use App\Modules\Susceptibility\Models\SusceptibilityTest;

/**
 * Antibiograma descartado: Workflow cancela su lectura y, si el aislamiento ya está
 * identificado, vuelve a dejar pendiente la preparación.
 */
final readonly class SusceptibilityTestCancelled
{
    public function __construct(
        public SusceptibilityTest $test,
        public bool $needsNewSetUp,
        public int $userId,
    ) {}
}
