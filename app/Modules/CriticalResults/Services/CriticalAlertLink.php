<?php

namespace App\Modules\CriticalResults\Services;

use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Models\CriticalAlert;

/**
 * Pantalla donde se atiende un resultado crítico, según su origen.
 */
class CriticalAlertLink
{
    public static function for(CriticalAlert $alert): string
    {
        return match ($alert->source) {
            CriticalAlertSource::PositiveGram => route('blood-cultures.show', $alert->sample_study_id),
            CriticalAlertSource::DirectExam => route('direct-exams.show', $alert->sample_study_id),
        };
    }
}
