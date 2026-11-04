<?php

namespace App\Modules\Susceptibility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\Breakpoint;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Configuration\Services\ActiveBreakpointSet;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\RecordIsolateTests;
use App\Modules\Identification\Data\IsolateTestInput;
use App\Modules\Identification\Queries\IsolateTestsQuery;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use App\Modules\Susceptibility\Actions\CancelSusceptibilityTest;
use App\Modules\Susceptibility\Actions\RecordSusceptibilityResults;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Data\AdditionalAntibiotic;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Susceptibility\Services\AstRuleApplier;
use App\Modules\Susceptibility\Services\AstSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SusceptibilityController extends Controller
{
    public function show(
        Request $request,
        Isolate $isolate,
        SampleHeader $header,
        ActiveBreakpointSet $activeSet,
        SetUpSusceptibilityTest $setUp,
        AstSchedule $schedule,
        AstRuleApplier $rules,
        IsolateTestsQuery $tests,
    ): Response {
        $this->authorizeSite($isolate);
        $isolate->load(['organism', 'culture.sampleStudy.protocolVersion', 'culture.sampleStudy.sample']);
        $test = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->current()
            ->with(['results.antibiotic.reportedAs', 'panel', 'breakpointSet'])->latest('id')->first();
        $set = $test->breakpointSet ?? $activeSet->resolve();
        $presumptive = $isolate->status === IsolateStatus::InIdentification;
        $applicable = $test === null ? collect() : $rules->forTest($test);
        $cancelled = SusceptibilityTest::query()->where('isolate_id', $isolate->id)
            ->where('status', SusceptibilityTestStatus::Cancelled->value)->with('panel')->orderBy('id')->get();

        return Inertia::render('susceptibility/Susceptibility', [
            'sample' => $header->for($isolate->culture->sampleStudy),
            'isolate' => [
                'ulid' => $isolate->ulid,
                'number' => $isolate->number,
                'organism' => $isolate->organism?->name,
                'group' => $isolate->organism?->organism_group->label(),
                'count_category' => $isolate->count_category,
                'presumptive' => $presumptive,
                'status' => $isolate->status->value,
            ],
            'allowPresumptive' => (bool) $isolate->culture->sampleStudy->protocolVersion->allow_presumptive_ast,
            'panels' => $isolate->status->isResolved() ? [] : $setUp->panelsFor($isolate)
                ->map(fn (AstPanel $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'is_vitek' => $p->is_vitek,
                    'is_reference_template' => $p->is_reference_template,
                    'antibiotic_ids' => $p->antibiotics->pluck('id')->values(),
                    'read_min_hours' => $p->read_min_hours,
                ])->values(),
            // Pruebas de mecanismo y complementarias (B5): se registran con el antibiograma.
            'mechanismTests' => $tests->catalog([IdentificationTestKind::Mechanism, IdentificationTestKind::Complementary]),
            'isolateTests' => array_values(array_filter($tests->forIsolate($isolate->id), fn (array $t) => in_array($t['kind'], ['mechanism', 'complementary'], true))),
            'cancelled' => $cancelled->map(fn (SusceptibilityTest $t) => [
                'panel' => $t->panel->name,
                'set_up_at' => $t->set_up_at->toIso8601String(),
                'cancelled_at' => $t->cancelled_at?->toIso8601String(),
                'reason' => $t->cancellation_reason,
            ])->values(),
            'methods' => array_map(fn (AstMethod $m) => ['value' => $m->value, 'label' => $m->label(), 'unit' => $m->unit()], AstMethod::cases()),
            // Para agregar por fuera del panel (D-0022).
            'antibiotics' => Antibiotic::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Antibiotic $a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->displayName()])->values(),
            'test' => $test === null ? null : [
                'ulid' => $test->ulid,
                'status' => $test->status->value,
                'method' => $test->method->value,
                'unit' => $test->method->unit(),
                'panel' => $test->panel->name,
                'is_vitek' => $test->panel->is_vitek,
                'set_up_at' => $test->set_up_at->toIso8601String(),
                'available_at' => $schedule->availableAt($test)?->toIso8601String() ?? $test->set_up_at->toIso8601String(),
                'panel_covers_group' => $isolate->organism === null || $test->panel->organism_groups->contains($isolate->organism->organism_group),
                'results' => $test->results->map(fn (SusceptibilityResult $r) => [
                    'antibiotic_id' => $r->antibiotic_id,
                    'method' => $r->method->value,
                    'unit' => $r->method->unit(),
                    'is_additional' => $r->is_additional,
                    'addition_reason' => $r->addition_reason,
                    'read' => $r->isRead(),
                    'antibiotic' => $r->antibiotic->displayName(),
                    'reported_as' => $r->antibiotic->reportedAs?->name,
                    'code' => $r->antibiotic->code,
                    'rules' => $applicable->get($r->antibiotic_id, collect())->map(fn (AstRule $rule) => [
                        'effect' => $rule->effect->value,
                        'note' => $rule->note(),
                        'interpretation' => $rule->interpretation?->value,
                    ])->values(),
                    'rule_notes' => $r->rule_notes,
                    'mic_operator' => $r->mic_operator,
                    'value' => $r->measured_value === null ? null : (float) $r->measured_value,
                    'automatic' => $r->automatic_interpretation?->value,
                    'reviewed' => $r->reviewed_interpretation?->value,
                    'final' => $r->finalInterpretation()?->value,
                    'override_reason' => $r->override_reason,
                    'is_reported' => $r->is_reported,
                ])->values(),
                // Puntos de corte para mostrar la interpretación en vivo mientras se cargan los valores.
                'breakpoints' => $this->breakpointsFor($set, $isolate, $test),
            ],
            'breakpointSet' => $set === null ? null : ['name' => $set->name, 'edition' => $set->edition, 'is_synthetic' => $set->is_synthetic],
            'can' => [
                'setup' => $request->user()?->can(Permission::AstSetup->value) ?? false,
                'tests' => $request->user()?->can(Permission::IdentificationPerform->value) || $request->user()?->can(Permission::AstRead->value),
                'read' => $request->user()?->can(Permission::AstRead->value) ?? false,
                'override' => $request->user()?->can(Permission::AstInterpretationOverride->value) ?? false,
            ],
        ]);
    }

    public function setUp(Request $request, Isolate $isolate, SetUpSusceptibilityTest $setUp): RedirectResponse
    {
        $this->authorizeSite($isolate);
        abort_unless($request->user()?->can(Permission::AstSetup->value), 403);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(AstMethod::class)],
            'panel_id' => ['nullable', 'integer'],
            'set_up_at' => ['nullable', 'date'],
            ...self::ADDITIONAL_RULES,
        ]);

        /** @var User $user */
        $user = $request->user();
        $setUp->handle(
            $user,
            $isolate,
            AstMethod::from($validated['method']),
            isset($validated['panel_id']) ? (int) $validated['panel_id'] : null,
            $this->additional($validated),
            isset($validated['set_up_at']) ? Date::parse($validated['set_up_at']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antibiograma preparado. La lectura queda programada.']);

        return to_route('workbenches.show', 'susceptibility');
    }

    public function read(Request $request, Isolate $isolate, RecordSusceptibilityResults $record): RedirectResponse
    {
        $this->authorizeSite($isolate);
        abort_unless($request->user()?->can(Permission::AstRead->value), 403);
        $test = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->current()->latest('id')->firstOrFail();

        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.antibiotic_id' => ['required', 'integer'],
            'results.*.mic_operator' => ['nullable', Rule::in(['<=', '=', '>'])],
            'results.*.value' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'results.*.reviewed' => ['nullable', Rule::enum(Interpretation::class)],
            'results.*.override_reason' => ['nullable', 'string', 'max:500'],
            'results.*.is_reported' => ['required', 'boolean'],
            ...self::ADDITIONAL_RULES,
        ], [], ['results.*.value' => 'valor']);

        /** @var User $user */
        $user = $request->user();
        $record->handle($user, $test, array_map(fn (array $r) => new ResultInput(
            antibioticId: (int) $r['antibiotic_id'],
            micOperator: $r['mic_operator'] ?? null,
            value: isset($r['value']) ? (float) $r['value'] : null,
            reviewed: isset($r['reviewed']) ? Interpretation::from($r['reviewed']) : null,
            overrideReason: $r['override_reason'] ?? null,
            reported: (bool) $r['is_reported'],
        ), array_values($validated['results'])), EarlyConfirmation::fromRequest($request), $this->additional($validated));

        Inertia::flash('toast', ['type' => 'success', 'message' => ($validated['additional'] ?? []) === []
            ? 'Antibiograma leído.'
            : 'Lectura registrada. Los antibióticos agregados quedan para leer.']);

        return to_route('workbenches.show', 'susceptibility');
    }

    public function cancel(Request $request, Isolate $isolate, CancelSusceptibilityTest $cancel): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $test = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->current()->latest('id')->firstOrFail();
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:500']], [], ['cancellation_reason' => 'motivo']);

        /** @var User $user */
        $user = $request->user();
        $cancel->handle($user, $test, $validated['cancellation_reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antibiograma descartado. Se puede preparar otro.']);

        return back();
    }

    /**
     * Pruebas de mecanismo o complementarias del antibiograma (sinergia BLEE, carbapenemasa…).
     */
    public function tests(Request $request, Isolate $isolate, RecordIsolateTests $record): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'tests' => ['required', 'array', 'min:1', 'max:20'],
            'tests.*.test_id' => ['required', 'integer'],
            'tests.*.result_code' => ['nullable', 'string', 'max:20'],
            'tests.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $test = SusceptibilityTest::currentFor($isolate->id);

        /** @var User $user */
        $user = $request->user();
        $record->handle($user, $isolate, array_map(fn (array $t) => new IsolateTestInput(
            testId: (int) $t['test_id'],
            resultCode: filled($t['result_code'] ?? null) ? $t['result_code'] : null,
            notes: $t['notes'] ?? null,
        ), array_values($validated['tests'])), $test?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pruebas registradas.']);

        return back();
    }

    /** Antibióticos por fuera del panel (D-0022). */
    private const ADDITIONAL_RULES = [
        'additional' => ['nullable', 'array'],
        'additional.*.antibiotic_id' => ['required', 'integer'],
        'additional.*.method' => ['required', 'in:disk,mic'],
        'additional.*.reason' => ['nullable', 'string', 'max:500'],
    ];

    /**
     * @param  array<string, mixed>  $validated
     * @return list<AdditionalAntibiotic>
     */
    private function additional(array $validated): array
    {
        /** @var list<array{antibiotic_id: int, method: string, reason?: string|null}> $rows */
        $rows = array_values($validated['additional'] ?? []);

        return array_map(fn (array $a) => new AdditionalAntibiotic(
            antibioticId: (int) $a['antibiotic_id'],
            method: AstMethod::from($a['method']),
            reason: $a['reason'] ?? null,
        ), $rows);
    }

    /**
     * Por antibiótico y método ("id|método").
     *
     * @return array<string, array{s: float, r: float}>
     */
    private function breakpointsFor(?BreakpointSet $set, Isolate $isolate, SusceptibilityTest $test): array
    {
        if ($set === null || $isolate->organism === null) {
            return [];
        }

        return Breakpoint::query()
            ->where('breakpoint_set_id', $set->id)
            ->where('organism_group', $isolate->organism->organism_group->value)
            ->get()
            ->mapWithKeys(fn (Breakpoint $b) => [$b->antibiotic_id.'|'.$b->method->value => ['s' => (float) $b->susceptible_value, 'r' => (float) $b->resistant_value]])
            ->all();
    }

    private function authorizeSite(Isolate $isolate): void
    {
        abort_unless($isolate->culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
