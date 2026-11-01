<?php

namespace App\Modules\Susceptibility\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Breakpoint;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Configuration\Services\BreakpointInterpreter;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lectura del antibiograma: valores medidos → interpretación automática con el set de puntos
 * de corte vigente (que queda congelado en el test) → revisión. Cambiar la interpretación
 * automática exige motivo y el permiso correspondiente. Sin punto de corte cargado, la
 * interpretación la ingresa el bacteriólogo.
 */
class RecordSusceptibilityResults
{
    public function __construct(
        private readonly BreakpointInterpreter $interpreter,
        private readonly CultureClosure $closure,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  list<ResultInput>  $inputs
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, SusceptibilityTest $test, array $inputs): SusceptibilityTest
    {
        return DB::transaction(function () use ($user, $test, $inputs) {
            /** @var SusceptibilityTest $test */
            $test = SusceptibilityTest::query()->lockForUpdate()->with(['results', 'isolate.organism'])->findOrFail($test->id);

            if ($test->status !== SusceptibilityTestStatus::SetUp) {
                throw ValidationException::withMessages(['results' => 'Este antibiograma ya fue leído.']);
            }

            $minHours = $test->isolate->culture->sampleStudy->protocolVersion->ast_read_min_hours ?? 0;
            if ($test->set_up_at->copy()->addHours($minHours)->isFuture()) {
                throw ValidationException::withMessages(['results' => 'El antibiograma todavía no cumplió la incubación mínima del protocolo.']);
            }

            $set = $this->activeBreakpointSet();
            $group = $test->isolate->organism?->organism_group;
            $breakpoints = $set === null || $group === null ? collect() : Breakpoint::query()
                ->where('breakpoint_set_id', $set->id)
                ->where('organism_group', $group->value)
                ->where('method', $test->method->value)
                ->get()
                ->keyBy('antibiotic_id');

            $byAntibiotic = collect($inputs)->keyBy('antibioticId');
            $errors = [];
            $overrides = [];

            foreach ($test->results as $index => $result) {
                /** @var SusceptibilityResult $result */
                $input = $byAntibiotic->get($result->antibiotic_id);
                if ($input === null || $input->value === null) {
                    $errors["results.{$index}.value"] = 'Falta el valor medido.';

                    continue;
                }

                $automatic = $this->interpreter->interpret($breakpoints->get($result->antibiotic_id), $input->value);
                $reviewed = $input->reviewed !== null && $input->reviewed !== $automatic ? $input->reviewed : null;

                if ($automatic === null && $input->reviewed === null) {
                    $errors["results.{$index}.reviewed"] = 'Sin punto de corte cargado: ingresá la interpretación.';

                    continue;
                }
                if ($automatic !== null && $reviewed !== null) {
                    if (! $user->can(Permission::AstInterpretationOverride->value)) {
                        throw new AuthorizationException('No tenés permiso para modificar interpretaciones.');
                    }
                    if (blank($input->overrideReason)) {
                        $errors["results.{$index}.override_reason"] = 'Indicá el motivo del cambio de interpretación.';

                        continue;
                    }
                    $overrides[] = ['antibiotic_id' => $result->antibiotic_id, 'automatic' => $automatic->value, 'reviewed' => $reviewed->value];
                }

                $result->fill([
                    'mic_operator' => $input->micOperator,
                    'measured_value' => $input->value,
                    'automatic_interpretation' => $automatic,
                    // Sin automática, lo que ingresa el bacteriólogo es la interpretación revisada.
                    'reviewed_interpretation' => $automatic === null ? $input->reviewed : $reviewed,
                    'override_reason' => $reviewed !== null ? $input->overrideReason : null,
                    'is_reported' => $input->reported,
                ]);
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $test->results->each->save();
            $test->update([
                'status' => SusceptibilityTestStatus::Completed,
                'breakpoint_set_id' => $set?->id,
                'read_by' => $user->id,
                'read_at' => Date::now(),
                'lock_version' => $test->lock_version + 1,
            ]);

            $this->auditor->record(SusceptibilityAuditAction::Read, $test, after: [
                'breakpoint_set_id' => $set?->id,
                'results' => $test->results->map(fn (SusceptibilityResult $r) => [
                    'antibiotic_id' => $r->antibiotic_id,
                    'value' => $r->measured_value,
                    'interpretation' => $r->finalInterpretation()?->value,
                    'reported' => $r->is_reported,
                ])->values()->all(),
            ]);
            foreach ($overrides as $override) {
                $reason = $byAntibiotic->get($override['antibiotic_id'])?->overrideReason;
                $this->auditor->record(SusceptibilityAuditAction::InterpretationOverridden, $test, before: ['interpretation' => $override['automatic']], after: ['interpretation' => $override['reviewed'], 'antibiotic_id' => $override['antibiotic_id']], reason: $reason);
            }

            event(new SusceptibilityTestRead($test, $user->id));

            $this->closure->resolveIsolate($test->isolate);

            return $test;
        });
    }

    /**
     * Set vigente. En producción solo sets reales; el ficticio de demo nunca se usa allí.
     */
    private function activeBreakpointSet(): ?BreakpointSet
    {
        return BreakpointSet::query()
            ->where('status', ProtocolVersionStatus::Active->value)
            ->when(app()->isProduction(), fn ($q) => $q->where('is_synthetic', false))
            ->orderBy('is_synthetic')
            ->latest('id')
            ->first();
    }
}
