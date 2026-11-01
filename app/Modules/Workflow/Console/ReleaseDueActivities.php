<?php

namespace App\Modules\Workflow\Console;

use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Pasa a READY las actividades programadas cuyo momento llegó
 * (p. ej., la lectura cuando se cumple la incubación mínima). Corre cada minuto.
 */
class ReleaseDueActivities extends Command
{
    protected $signature = 'activities:release';

    protected $description = 'Libera las actividades programadas que ya están disponibles';

    public function handle(): int
    {
        $released = Activity::query()
            ->where('status', ActivityStatus::Scheduled->value)
            ->where('available_at', '<=', Date::now())
            ->update([
                'status' => ActivityStatus::Ready->value,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);

        $this->components->info("{$released} actividades disponibles.");

        return self::SUCCESS;
    }
}
