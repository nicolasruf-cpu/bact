<?php

namespace App\Modules\Susceptibility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Breakpoint;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Configuration\Services\ActiveBreakpointSet;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use App\Modules\Susceptibility\Actions\RecordSusceptibilityResults;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SusceptibilityController extends Controller
{
    public function show(Request $request, Isolate $isolate, SampleHeader $header, ActiveBreakpointSet $activeSet): Response
    {
        $this->authorizeSite($isolate);
        $isolate->load(['organism', 'culture.sampleStudy.protocolVersion']);
        $test = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->with(['results.antibiotic', 'panel', 'breakpointSet'])->first();
        $set = $activeSet->resolve();

        return Inertia::render('susceptibility/Susceptibility', [
            'sample' => $header->for($isolate->culture->sampleStudy),
            'isolate' => [
                'ulid' => $isolate->ulid,
                'number' => $isolate->number,
                'organism' => $isolate->organism?->name,
                'group' => $isolate->organism?->organism_group->label(),
                'count_category' => $isolate->count_category,
            ],
            'panels' => $isolate->organism === null ? [] : AstPanel::query()
                ->where('organism_group', $isolate->organism->organism_group->value)
                ->where('is_active', true)->get(['id', 'code', 'name', 'is_reference_template']),
            'methods' => array_map(fn (AstMethod $m) => ['value' => $m->value, 'label' => $m->label(), 'unit' => $m->unit()], AstMethod::cases()),
            'test' => $test === null ? null : [
                'ulid' => $test->ulid,
                'status' => $test->status->value,
                'method' => $test->method->value,
                'unit' => $test->method->unit(),
                'panel' => $test->panel->name,
                'set_up_at' => $test->set_up_at->toIso8601String(),
                'available_at' => $test->set_up_at->copy()->addHours((int) $isolate->culture->sampleStudy->protocolVersion->ast_read_min_hours)->toIso8601String(),
                'results' => $test->results->map(fn (SusceptibilityResult $r) => [
                    'antibiotic_id' => $r->antibiotic_id,
                    'antibiotic' => $r->antibiotic->name,
                    'code' => $r->antibiotic->code,
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
        ]);

        /** @var User $user */
        $user = $request->user();
        $setUp->handle($user, $isolate, AstMethod::from($validated['method']), isset($validated['panel_id']) ? (int) $validated['panel_id'] : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antibiograma preparado. La lectura queda programada.']);

        return to_route('workbenches.show', 'susceptibility');
    }

    public function read(Request $request, Isolate $isolate, RecordSusceptibilityResults $record): RedirectResponse
    {
        $this->authorizeSite($isolate);
        abort_unless($request->user()?->can(Permission::AstRead->value), 403);
        $test = SusceptibilityTest::query()->where('isolate_id', $isolate->id)->firstOrFail();

        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.antibiotic_id' => ['required', 'integer'],
            'results.*.mic_operator' => ['nullable', Rule::in(['<=', '=', '>'])],
            'results.*.value' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'results.*.reviewed' => ['nullable', Rule::enum(Interpretation::class)],
            'results.*.override_reason' => ['nullable', 'string', 'max:500'],
            'results.*.is_reported' => ['required', 'boolean'],
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
        ), array_values($validated['results'])));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antibiograma leído.']);

        return to_route('workbenches.show', 'susceptibility');
    }

    /**
     * @return array<int, array{s: float, r: float}>
     */
    private function breakpointsFor(?BreakpointSet $set, Isolate $isolate, SusceptibilityTest $test): array
    {
        if ($set === null || $isolate->organism === null) {
            return [];
        }

        return Breakpoint::query()
            ->where('breakpoint_set_id', $set->id)
            ->where('organism_group', $isolate->organism->organism_group->value)
            ->where('method', $test->method->value)
            ->get()
            ->mapWithKeys(fn (Breakpoint $b) => [$b->antibiotic_id => ['s' => (float) $b->susceptible_value, 'r' => (float) $b->resistant_value]])
            ->all();
    }

    private function authorizeSite(Isolate $isolate): void
    {
        abort_unless($isolate->culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
