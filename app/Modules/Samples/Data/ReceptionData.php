<?php

namespace App\Modules\Samples\Data;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Patients\Data\PatientData;

/**
 * Una entrega: los datos de la orden (nueva o existente) y sus muestras.
 * Si `orderNumber` corresponde a una orden existente de la sede, las muestras se suman a ella
 * y se conservan su paciente, médico y prioridad.
 */
final readonly class ReceptionData
{
    /**
     * @param  list<IncomingSampleData>  $samples
     */
    public function __construct(
        public string $idempotencyKey,
        public ?string $orderNumber,
        public PatientData $patient,
        public ?string $requestingService,
        public ?string $requestingPhysician,
        public Priority $priority,
        public array $samples,
    ) {}
}
