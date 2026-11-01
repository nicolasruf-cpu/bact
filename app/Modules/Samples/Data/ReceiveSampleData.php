<?php

namespace App\Modules\Samples\Data;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Patients\Data\PatientData;
use Carbon\CarbonImmutable;

final readonly class ReceiveSampleData
{
    /**
     * @param  list<int>  $studyIds
     */
    public function __construct(
        public string $idempotencyKey,
        public string $barcode,
        public PatientData $patient,
        public int $sampleTypeId,
        public array $studyIds,
        public Priority $priority,
        public ?CarbonImmutable $collectedAt,
        public ?string $requestingService,
        public ?string $requestingPhysician,
        public ?string $notes,
    ) {}
}
