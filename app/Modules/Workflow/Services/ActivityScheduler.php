<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Orders\Enums\Priority;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Crea actividades en forma idempotente: la clave (sujeto, tipo, intento) es única,
 * así que si un evento se procesa dos veces no se duplica nada.
 */
class ActivityScheduler
{
    public function schedule(
        ActivityType $type,
        Model $subject,
        int $sampleId,
        int $siteId,
        Priority $priority,
        CarbonInterface $availableAt,
        ?CarbonInterface $dueAt = null,
        int $attempt = 1,
        ?int $causedByActivityId = null,
    ): Activity {
        return Activity::query()->firstOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'type' => $type,
                'attempt' => $attempt,
            ],
            [
                'sample_id' => $sampleId,
                'site_id' => $siteId,
                'status' => $availableAt->lessThanOrEqualTo(Date::now()) ? ActivityStatus::Ready : ActivityStatus::Scheduled,
                'priority' => $priority,
                'available_at' => $availableAt,
                'due_at' => $dueAt,
                'caused_by_activity_id' => $causedByActivityId,
            ],
        );
    }
}
