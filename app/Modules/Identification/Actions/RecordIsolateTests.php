<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Data\IsolateTestInput;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\IsolateTestsSetUp;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra pruebas sobre un aislamiento (revisión de marchas B1, B2, B5): el Gram de la
 * colonia, las pruebas rápidas, las bioquímicas y las de mecanismo, con el catálogo del
 * laboratorio. Con resultado, la prueba queda leída en el momento. Sin resultado queda
 * sembrada: se lee al cumplirse la incubación de la prueba, y Workflow programa esa lectura.
 */
class RecordIsolateTests
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  list<IsolateTestInput>  $inputs
     * @return Collection<int, IsolateTest>
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, array $inputs, ?int $susceptibilityTestId = null): Collection
    {
        if ($inputs === []) {
            throw ValidationException::withMessages(['tests' => 'Agregá al menos una prueba.']);
        }

        return DB::transaction(function () use ($user, $isolate, $inputs, $susceptibilityTestId) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->with('culture')->findOrFail($isolate->id);
            if ($isolate->culture->status->isClosed()) {
                throw ValidationException::withMessages(['tests' => 'El cultivo ya está cerrado.']);
            }

            $catalog = IdentificationTest::query()->whereIn('id', array_map(fn (IsolateTestInput $i) => $i->testId, $inputs))->get()->keyBy('id');
            $now = Date::now();
            $created = collect();

            foreach ($inputs as $index => $input) {
                /** @var IdentificationTest|null $test */
                $test = $catalog->get($input->testId);
                if ($test === null || ! $test->is_active) {
                    throw ValidationException::withMessages(["tests.{$index}.test_id" => 'La prueba no existe o está desactivada.']);
                }

                $this->authorize($user, $test, $input->resultCode !== null);

                $label = null;
                if ($input->resultCode !== null) {
                    $label = $test->resultLabel($input->resultCode);
                    if ($label === null) {
                        throw ValidationException::withMessages(["tests.{$index}.result_code" => "Resultado no válido para {$test->name}."]);
                    }
                } elseif (! $test->needsIncubation()) {
                    throw ValidationException::withMessages(["tests.{$index}.result_code" => "{$test->name} se lee en el momento: indicá el resultado."]);
                }

                $created->push(IsolateTest::create([
                    'isolate_id' => $isolate->id,
                    'identification_test_id' => $test->id,
                    'susceptibility_test_id' => $susceptibilityTestId,
                    'set_up_at' => $now,
                    'set_up_by' => $user->id,
                    'readable_at' => $now->copy()->addHours((int) $test->incubation_hours),
                    'result_code' => $input->resultCode,
                    'result_label' => $label,
                    'notes' => filled($input->notes) ? $input->notes : null,
                    'read_at' => $label !== null ? $now : null,
                    'read_by' => $label !== null ? $user->id : null,
                ]));
            }

            $this->auditor->record(IdentificationAuditAction::TestsRecorded, $isolate, after: [
                'tests' => $created->map(fn (IsolateTest $t) => [
                    'test' => $catalog[$t->identification_test_id]->code,
                    'result' => $t->result_code,
                ])->all(),
            ]);

            $pending = $created->reject(fn (IsolateTest $t) => $t->isRead());
            if ($pending->isNotEmpty()) {
                event(new IsolateTestsSetUp($isolate, $pending->min('readable_at'), $user->id));
            }

            return $created;
        });
    }

    /**
     * Sembrar bioquímicas y leerlas son permisos distintos (el Auxiliar siembra, Q-S1).
     */
    private function authorize(User $user, IdentificationTest $test, bool $withResult): void
    {
        $permission = match (true) {
            ! $withResult => Permission::IdentificationBiochemSetup,
            $test->kind === IdentificationTestKind::Biochemical => Permission::IdentificationBiochemRead,
            default => Permission::IdentificationPerform,
        };

        if (! $user->can($permission->value) && ! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para registrar esta prueba.');
        }
    }
}
