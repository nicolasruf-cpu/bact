<?php

namespace App\Console\Commands;

use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * SOLO DEMO/DESARROLLO: simula el paso de N horas (siembras, lecturas, antibiogramas y
 * actividades en curso), para probar el circuito sin esperar la incubación real.
 * Nunca corre en producción. No toca la auditoría.
 */
class AdvanceDemoClock extends Command
{
    protected $signature = 'demo:advance {hours=24 : horas a simular}';

    protected $description = 'SOLO DEMO: simula el paso de N horas y libera las actividades disponibles';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Este comando no se puede usar en producción.');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->argument('hours'));

        DB::transaction(function () use ($hours) {
            $shift = fn (Model $model, string ...$columns) => $model->forceFill(
                collect($columns)->mapWithKeys(fn ($c) => [$c => $model->getAttribute($c)?->copy()->subHours($hours)])->all(),
            )->saveQuietly();

            Culture::query()->whereIn('status', [CultureStatus::Incubating->value, CultureStatus::Workup->value])
                ->each(fn (Culture $c) => $shift($c, 'inoculated_at'));
            Reading::query()->whereHas('culture', fn ($q) => $q->where('status', CultureStatus::Incubating->value))
                ->each(fn (Reading $r) => DB::table('readings')->where('id', $r->id)->update(['read_at' => $r->read_at->copy()->subHours($hours)]));
            SusceptibilityTest::query()->where('status', SusceptibilityTestStatus::SetUp->value)
                ->each(fn (SusceptibilityTest $t) => $shift($t, 'set_up_at'));

            Activity::query()->whereIn('status', [ActivityStatus::Scheduled->value, ActivityStatus::Ready->value, ActivityStatus::InProgress->value])
                ->each(function (Activity $activity) use ($shift) {
                    $shift($activity, 'available_at', 'due_at');
                    if ($activity->status === ActivityStatus::Scheduled && $activity->available_at->lessThanOrEqualTo(Date::now())) {
                        $activity->forceFill(['status' => ActivityStatus::Ready])->saveQuietly();
                    }
                });
        });

        $ready = Activity::query()->where('status', ActivityStatus::Ready->value)->count();
        $this->components->info("Se simularon {$hours} h. Actividades disponibles: {$ready}.");

        return self::SUCCESS;
    }
}
