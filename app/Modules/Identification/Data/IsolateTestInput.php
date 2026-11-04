<?php

namespace App\Modules\Identification\Data;

/**
 * Una prueba a registrar sobre un aislamiento. Sin resultado, queda sembrada y se lee
 * cuando se cumpla su incubación.
 */
final readonly class IsolateTestInput
{
    public function __construct(
        public int $testId,
        public ?string $resultCode = null,
        public ?string $notes = null,
    ) {}
}
