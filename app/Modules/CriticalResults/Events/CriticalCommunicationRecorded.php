<?php

namespace App\Modules\CriticalResults\Events;

use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\CriticalResults\Models\CriticalCommunication;

/**
 * Intento de comunicación registrado. Si fue exitoso, Workflow completa la actividad.
 */
final readonly class CriticalCommunicationRecorded
{
    public function __construct(
        public CriticalAlert $alert,
        public CriticalCommunication $communication,
        public int $userId,
    ) {}
}
