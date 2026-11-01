<?php

namespace App\Modules\Samples\Data;

use Carbon\CarbonImmutable;

final readonly class IncomingSampleData
{
    /**
     * @param  list<int>  $studyIds
     */
    public function __construct(
        public string $barcode,
        public int $sampleTypeId,
        public array $studyIds,
        public ?CarbonImmutable $collectedAt,
        public ?string $notes,
    ) {}
}
