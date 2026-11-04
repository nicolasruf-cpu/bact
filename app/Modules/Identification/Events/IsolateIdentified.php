<?php

namespace App\Modules\Identification\Events;

use App\Modules\Cultures\Models\Isolate;
use App\Modules\Susceptibility\Models\SusceptibilityTest;

/**
 * Identificación definitiva registrada. Si requiere antibiograma, Workflow crea su preparación,
 * salvo que ya se haya preparado con la identificación presuntiva (A1). Si no lo requiere y
 * había uno preparado, se descartó: Workflow cancela su lectura.
 */
final readonly class IsolateIdentified
{
    public function __construct(
        public Isolate $isolate,
        public bool $requiresSusceptibility,
        public int $userId,
        public ?SusceptibilityTest $presumptiveTest = null,
    ) {}
}
