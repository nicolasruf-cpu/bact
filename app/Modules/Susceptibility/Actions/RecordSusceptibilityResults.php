<?php

namespace App\Modules\Susceptibility\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\Breakpoint;
use App\Modules\Configuration\Services\ActiveBreakpointSet;
use App\Modules\Configuration\Services\BreakpointInterpreter;
use App\Modules\Configuration\Services\IncubationTimeCheck;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identification\Events\InstrumentRunResulted;
use App\Modules\Identification\Models\InstrumentRun;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Susceptibility\Data\AdditionalAntibiotic;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityAntibioticsAdded;
use App\Modules\Susceptibility\Events\SusceptibilityTestRead;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Susceptibility\Services\AdditionalAntibiotics;
use App\Modules\Susceptibility\Services\AstRuleApplier;
use App\Modules\Susceptibility\Services\AstSchedule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lectura del antibiograma: valores medidos → interpretación automática con el set de puntos
 * de corte vigente (que queda congelado en el test) → revisión. Cambiar la interpretación
 * automática exige motivo y el permiso correspondiente. Sin punto de corte cargado, la
 * interpretación la ingresa el bacteriólogo. Antes de la incubación mínima (la del panel o la
 * del protocolo, A2) se puede leer igual, con confirmación (D-0020).
 *
 * Se leen los antibióticos que faltan (D-0022). Al leer se pueden agregar antibióticos por
 * fuera del panel: tienen su propia lectura y el aislamiento queda resuelto recién cuando no
 * falta leer ninguno. Se lee con la identificación definitiva: los puntos de corte dependen
 * del germen (A1). Las reglas del laboratorio (C2) se aplican al leer y quedan anotadas.
 */
class RecordSusceptibilityResults
{
    public function __construct(
        private readonly BreakpointInterpreter $interpreter,
        private readonly ActiveBreakpointSet $activeSet,
        private readonly CultureClosure $closure,
        private readonly IncubationTimeCheck $timeCheck,
        private readonly AdditionalAntibiotics $additionalAntibiotics,
        private readonly AstSchedule $schedule,
        private readonly AstRuleApplier $rules,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  list<ResultInput>  $inputs
     * @param  list<AdditionalAntibiotic>  $additional  para leer después
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, SusceptibilityTest $test, array $inputs, EarlyConfirmation $early = new EarlyConfirmation, array $additional = []): SusceptibilityTest
    {
        return DB::transaction(function () use ($user, $test, $inputs, $early, $additional) {
            /** @var SusceptibilityTest $test */
            $test = SusceptibilityTest::query()->lockForUpdate()->with(['results', 'isolate.organism', 'panel'])->findOrFail($test->id);

            if ($test->status !== SusceptibilityTestStatus::SetUp || $test->pendingResults()->isEmpty()) {
                throw ValidationException::withMessages(['results' => 'Este antibiograma ya fue leído o se descartó.']);
            }
            if ($test->isolate->organism === null || $test->isolate->status !== IsolateStatus::Identified) {
                throw ValidationException::withMessages(['results' => 'Confirmá la identificación definitiva antes de leer: los puntos de corte dependen del germen.']);
            }

            $pending = $test->pendingResults();
            $availableAt = $this->schedule->availableAt($test);
            if ($availableAt !== null) {
                $this->timeCheck->ensure($availableAt, $early, $test, 'Lectura del antibiograma');
            }

            // El set de puntos de corte queda congelado en la primera lectura.
            $set = $test->breakpoint_set_id !== null ? $test->breakpointSet : $this->activeSet->resolve();
            $group = $test->isolate->organism->organism_group;
            $breakpoints = $set === null ? collect() : Breakpoint::query()
                ->where('breakpoint_set_id', $set->id)
                ->where('organism_group', $group->value)
                ->get()
                ->keyBy(fn (Breakpoint $b) => $b->antibiotic_id.'|'.$b->method->value);
            $rules = $this->rules->forTest($test);

            $byAntibiotic = collect($inputs)->keyBy('antibioticId');
            $errors = [];
            $overrides = [];

            foreach ($pending as $index => $result) {
                /** @var SusceptibilityResult $result */
                $input = $byAntibiotic->get($result->antibiotic_id);
                if ($input === null || $input->value === null) {
                    $errors["results.{$index}.value"] = 'Falta el valor medido.';

                    continue;
                }

                /** @var Collection<int, AstRule> $applicable */
                $applicable = $rules->get($result->antibiotic_id, collect());
                $expert = $applicable->first(fn (AstRule $r) => $r->effect === AstRuleEffect::Interpret);

                $automatic = $this->interpreter->interpret($breakpoints->get($result->antibiotic_id.'|'.$result->method->value), $input->value);
                $reviewed = $input->reviewed !== null && $input->reviewed !== $automatic ? $input->reviewed : null;

                // Regla experta: fija la interpretación, salvo que el bacteriólogo la cambie con motivo.
                if ($expert !== null && $reviewed === null && $expert->interpretation !== $automatic) {
                    $reviewed = $expert->interpretation;
                    $input = $input->withOverride($expert->interpretation, $expert->note());
                } elseif ($automatic === null && $input->reviewed === null) {
                    $errors["results.{$index}.reviewed"] = 'Sin punto de corte cargado: ingresá la interpretación.';

                    continue;
                } elseif ($automatic !== null && $reviewed !== null) {
                    if (! $user->can(Permission::AstInterpretationOverride->value)) {
                        throw new AuthorizationException('No tenés permiso para modificar interpretaciones.');
                    }
                    if (blank($input->overrideReason)) {
                        $errors["results.{$index}.override_reason"] = 'Indicá el motivo del cambio de interpretación.';

                        continue;
                    }
                    $overrides[] = ['antibiotic_id' => $result->antibiotic_id, 'automatic' => $automatic->value, 'reviewed' => $reviewed->value];
                }

                $suppressed = $applicable->contains(fn (AstRule $r) => $r->effect === AstRuleEffect::Suppress);
                $notes = $applicable->map(fn (AstRule $r) => $r->note())->filter()->implode(' · ');

                $result->fill([
                    'mic_operator' => $result->method === AstMethod::Mic ? $input->micOperator : null,
                    'measured_value' => $input->value,
                    'automatic_interpretation' => $automatic,
                    // Sin automática, lo que ingresa el bacteriólogo es la interpretación revisada.
                    'reviewed_interpretation' => $automatic === null ? ($reviewed ?? $input->reviewed) : $reviewed,
                    'override_reason' => $reviewed !== null || $automatic === null ? $input->overrideReason : null,
                    'rule_notes' => $notes !== '' ? $notes : null,
                    'is_reported' => $suppressed ? false : $input->reported,
                    'read_at' => Date::now(),
                ]);
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $pending->each->save();
            $now = Date::now();
            $this->additionalAntibiotics->add($user, $test, $additional, $now);
            $complete = $additional === [];

            $test->update([
                'status' => $complete ? SusceptibilityTestStatus::Completed : SusceptibilityTestStatus::SetUp,
                'breakpoint_set_id' => $set?->id,
                'read_by' => $user->id,
                'read_at' => Date::now(),
                'lock_version' => $test->lock_version + 1,
            ]);

            $this->auditor->record(SusceptibilityAuditAction::Read, $test, after: [
                'breakpoint_set_id' => $set?->id,
                'results' => $pending->map(fn (SusceptibilityResult $r) => [
                    'method' => $r->method->value,
                    'antibiotic_id' => $r->antibiotic_id,
                    'value' => $r->measured_value,
                    'interpretation' => $r->finalInterpretation()?->value,
                    'reported' => $r->is_reported,
                    'rules' => $r->rule_notes,
                ])->values()->all(),
            ]);
            foreach ($overrides as $override) {
                $reason = $byAntibiotic->get($override['antibiotic_id'])?->overrideReason;
                $this->auditor->record(SusceptibilityAuditAction::InterpretationOverridden, $test, before: ['interpretation' => $override['automatic']], after: ['interpretation' => $override['reviewed'], 'antibiotic_id' => $override['antibiotic_id']], reason: $reason);
            }

            // Resultado de la tarjeta de antibiograma del VITEK (B3).
            if ($test->instrument_run_id !== null) {
                $run = InstrumentRun::query()->lockForUpdate()->find($test->instrument_run_id);
                if ($run !== null && $run->ast_result_at === null) {
                    $run->update(['ast_result_at' => $now]);
                    event(new InstrumentRunResulted($run, $user->id));
                }
            }

            event(new SusceptibilityTestRead($test, $user->id));

            if ($complete) {
                $this->closure->resolveIsolate($test->isolate);
            } else {
                event(new SusceptibilityAntibioticsAdded($test, $user->id, $now));
            }

            return $test;
        });
    }
}
