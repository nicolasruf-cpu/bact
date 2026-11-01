<?php

namespace App\Modules\Cultures\Events;

use App\Modules\Cultures\Models\DirectExam;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Examen directo registrado. Workflow completa la actividad; si el protocolo lo marca
 * como crítico con microorganismos (`critical`), CriticalResults abre el crítico.
 */
final readonly class DirectExamRecorded
{
    public function __construct(
        public DirectExam $exam,
        public SampleStudy $sampleStudy,
        public string $summary,
        public bool $critical,
        public int $userId,
    ) {}
}
