<?php

namespace App\Modules\CriticalResults\Events;

use App\Modules\CriticalResults\Models\CriticalAlert;

/**
 * Resultado crítico abierto: Workflow crea la actividad de comunicación (prioridad inmediata).
 */
final readonly class CriticalAlertOpened
{
    public function __construct(
        public CriticalAlert $alert,
        public int $sampleId,
        public int $userId,
    ) {}
}
