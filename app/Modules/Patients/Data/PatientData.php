<?php

namespace App\Modules\Patients\Data;

use App\Modules\Patients\Enums\Sex;
use Carbon\CarbonImmutable;

final readonly class PatientData
{
    public function __construct(
        public string $documentNumber,
        public string $lastName,
        public string $firstName,
        public ?CarbonImmutable $birthDate,
        public Sex $sex,
    ) {}
}
