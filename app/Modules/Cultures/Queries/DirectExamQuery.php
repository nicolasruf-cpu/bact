<?php

namespace App\Modules\Cultures\Queries;

use App\Modules\Cultures\Models\DirectExam;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Examen directo de un estudio, con la redacción de las opciones del protocolo congelado.
 * Lo usan la pantalla del directo y el informe.
 */
class DirectExamQuery
{
    /**
     * @return array{label: string, lines: list<array{field: string, values: list<string>}>, description: string|null, organisms_seen: bool, performed_by: string, performed_at: string}|null
     */
    public function forStudy(SampleStudy $study): ?array
    {
        $settings = $study->protocolVersion->directExamSettings();
        /** @var DirectExam|null $exam */
        $exam = DirectExam::query()->where('sample_study_id', $study->id)->with('performer')->first();

        if ($settings === null || $exam === null) {
            return null;
        }

        return [
            'label' => $settings->label,
            'lines' => $settings->describe($exam->results),
            'description' => $exam->description,
            'organisms_seen' => $exam->organisms_seen,
            'performed_by' => $exam->performer->name,
            'performed_at' => $exam->performed_at->toIso8601String(),
        ];
    }
}
