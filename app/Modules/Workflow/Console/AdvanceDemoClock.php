<?php

namespace App\Modules\Workflow\Console;

use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * SOLO DEMO/DESARROLLO: adelanta las actividades programadas N horas, para probar
 * lecturas sin esperar la incubación real. Nunca corre en producción.
 */
class AdvanceDemoClock extends Command
{
    protected $signature = 'demo:advance {hours=24 : horas a adelantar}';

    protected $description = 'SOLO DEMO: adelanta N horas las actividades programadas y las libera';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Este comando no se puede usar en producción.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->argument('hours'));
        $shifted = 0;
        $released = 0;

        Activity::query()
            ->whereIn('status', [ActivityStatus::Scheduled->value, ActivityStatus::Ready->value])
            ->each(function (Activity $activity) use ($hours, &$shifted, &$released) {
                $activity->available_at = $activity->available_at->copy()->subHours($hours);
                $activity->due_at = $activity->due_at?->copy()->subHours($hours);

                if ($activity->status === ActivityStatus::Scheduled && $activity->available_at->lessThanOrEqualTo(Date::now())) {
                    $activity->status = ActivityStatus::Ready;
                    $released++;
                }

                $activity->save();
                $shifted++;
            });

        $this->components->info("Adelantadas {$hours} h: {$shifted} actividades; {$released} pasaron a disponibles.");

        return self::SUCCESS;
    }
}
