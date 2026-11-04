<?php

namespace App\Modules\Identification\Data;

use Carbon\CarbonInterface;

/**
 * Datos de una carga en el equipo. Las tarjetas de identificación y de antibiograma se cargan
 * juntas, con la misma suspensión; puede ir una sola.
 */
final readonly class InstrumentLoadData
{
    public function __construct(
        public string $instrument,
        public ?string $idCardType,
        public ?string $idCardNumber,
        public ?string $astCardType,
        public ?string $astCardNumber,
        public ?string $cassette,
        public ?string $position,
        public ?string $suspension,
        public ?CarbonInterface $loadedAt = null,
        public ?int $astPanelId = null,
    ) {}
}
