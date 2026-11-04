<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Services\IncubationTimeCheck;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\IsolateTestsRead;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lee pruebas sembradas de un aislamiento. Se pueden leer algunas y dejar otras para
 * después. Antes de cumplirse la incubación se registra igual, con confirmación (D-0020).
 */
class ReadIsolateTests
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly IncubationTimeCheck $timeCheck,
    ) {}

    /**
     * @param  array<int, string>  $results  id de la prueba del aislamiento => código del resultado
     * @param  array<int, string|null>  $notes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, array $results, array $notes = [], EarlyConfirmation $early = new EarlyConfirmation): void
    {
        if (! $user->can(Permission::IdentificationBiochemRead->value) && ! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para leer pruebas.');
        }
        if ($results === []) {
            throw ValidationException::withMessages(['results' => 'Indicá el resultado de al menos una prueba.']);
        }

        DB::transaction(function () use ($user, $isolate, $results, $notes, $early) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->findOrFail($isolate->id);

            $tests = IsolateTest::query()->where('isolate_id', $isolate->id)->whereNull('read_at')
                ->whereIn('id', array_keys($results))->with('test')->lockForUpdate()->get();
            if ($tests->count() !== count($results)) {
                throw ValidationException::withMessages(['results' => 'Alguna prueba ya fue leída o no es de este aislamiento.']);
            }

            /** @var IsolateTest $latest */
            $latest = $tests->sortBy('readable_at')->last();
            $this->timeCheck->ensure($latest->readable_at, $early, $isolate, 'Lectura de pruebas');

            $now = Date::now();
            foreach ($tests as $test) {
                $label = $test->test->resultLabel($results[$test->id]);
                if ($label === null) {
                    throw ValidationException::withMessages(["results.{$test->id}" => "Resultado no válido para {$test->test->name}."]);
                }
                $test->update([
                    'result_code' => $results[$test->id],
                    'result_label' => $label,
                    'notes' => filled($notes[$test->id] ?? null) ? $notes[$test->id] : $test->notes,
                    'read_at' => $now,
                    'read_by' => $user->id,
                ]);
            }

            $this->auditor->record(IdentificationAuditAction::TestsRead, $isolate, after: [
                'tests' => $tests->map(fn (IsolateTest $t) => ['test' => $t->test->code, 'result' => $t->result_code])->values()->all(),
            ]);

            $nextReadableAt = IsolateTest::query()->where('isolate_id', $isolate->id)->whereNull('read_at')->min('readable_at');

            event(new IsolateTestsRead($isolate, $nextReadableAt === null ? null : Date::parse($nextReadableAt), $user->id));
        });
    }
}
