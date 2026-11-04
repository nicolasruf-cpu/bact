<?php

namespace App\Modules\Identification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Services\AstPanelResolver;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\ChangeIsolateSignificance;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Actions\LoadInstrument;
use App\Modules\Identification\Actions\ReadIsolateSubculture;
use App\Modules\Identification\Actions\ReadIsolateTests;
use App\Modules\Identification\Actions\RecordIsolateTests;
use App\Modules\Identification\Actions\RecordReferralResult;
use App\Modules\Identification\Actions\ReferIsolate;
use App\Modules\Identification\Actions\SubcultureIsolate;
use App\Modules\Identification\Data\InstrumentLoadData;
use App\Modules\Identification\Data\IsolateTestInput;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identification\Enums\SubcultureResult;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identification\Models\InstrumentRun;
use App\Modules\Identification\Models\IsolateSubculture;
use App\Modules\Identification\Models\Referral;
use App\Modules\Identification\Queries\IsolateTestsQuery;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mesada de identificación de un aislamiento: Gram y pruebas, bioquímicas, VITEK, repiques,
 * derivaciones, significancia e identificación (revisión de marchas B1–B9, A1, A4).
 */
class IdentificationController extends Controller
{
    public function create(Request $request, Isolate $isolate, SampleHeader $header, AstPanelResolver $panels, IsolateTestsQuery $tests): Response
    {
        $this->authorizeFor($request, $isolate);
        $isolate->load(['culture.sampleStudy.sample', 'culture.sampleStudy.protocolVersion', 'plate.medium', 'organism']);
        $sampleTypeId = $isolate->culture->sampleStudy->sample->sample_type_id;
        $panelGroups = collect($panels->groupsWithPanel($sampleTypeId));
        $test = SusceptibilityTest::currentFor($isolate->id);
        $user = $request->user();

        return Inertia::render('identification/Identify', [
            'sample' => $header->for($isolate->culture->sampleStudy),
            'isolate' => [
                'ulid' => $isolate->ulid,
                'number' => $isolate->number,
                'morphotype' => $isolate->morphotype,
                'count_category' => $isolate->count_category,
                'plate' => $isolate->plate?->medium->name,
                'status' => $isolate->status->value,
                'status_label' => $isolate->status->label(),
                'organism' => $isolate->organism?->name,
                'significance' => $isolate->significance->value,
                'significance_label' => $isolate->significance->label(),
                'significance_reason' => $isolate->significance_reason,
                'culture_closed' => $isolate->culture->status->isClosed(),
                'study_id' => $isolate->culture->sample_study_id,
            ],
            'history' => Identification::query()->where('isolate_id', $isolate->id)->with(['organism', 'instrumentRun'])->orderBy('id')->get()
                ->map(fn (Identification $i) => [
                    'organism' => $i->organism?->name,
                    'method' => $i->method->label(),
                    'level' => $i->level->label(),
                    'level_value' => $i->level->value,
                    'tests' => $i->tests,
                    'confidence' => $i->confidence,
                    'notes' => $i->notes,
                    'card' => $i->instrumentRun ? trim(($i->instrumentRun->id_card_type ?? '').' '.($i->instrumentRun->id_card_number ?? '')) : null,
                    'identified_at' => $i->identified_at->toIso8601String(),
                ])->values(),
            'tests' => $tests->forIsolate($isolate->id),
            'catalog' => $tests->catalog([IdentificationTestKind::Gram, IdentificationTestKind::Rapid, IdentificationTestKind::Biochemical, IdentificationTestKind::Complementary]),
            'runs' => InstrumentRun::query()->where('isolate_id', $isolate->id)->orderBy('loaded_at')->get()
                ->map(fn (InstrumentRun $r) => [
                    'id' => $r->id,
                    'instrument' => $r->instrument,
                    'id_card' => $r->hasIdCard() ? trim($r->id_card_type.' '.($r->id_card_number ?? '')) : null,
                    'ast_card' => $r->hasAstCard() ? trim($r->ast_card_type.' '.($r->ast_card_number ?? '')) : null,
                    'cassette' => $r->cassette,
                    'position' => $r->position,
                    'suspension' => $r->suspension,
                    'loaded_at' => $r->loaded_at->toIso8601String(),
                    'id_result_at' => $r->id_result_at?->toIso8601String(),
                    'ast_result_at' => $r->ast_result_at?->toIso8601String(),
                    'pending' => $r->isPending(),
                ])->values(),
            'subcultures' => IsolateSubculture::query()->where('isolate_id', $isolate->id)->with('plate.medium')->orderBy('subcultured_at')->get()
                ->map(fn (IsolateSubculture $s) => [
                    'id' => $s->id,
                    'medium' => $s->plate->medium->name,
                    'atmosphere' => $s->plate->atmosphere->label(),
                    'reason' => $s->reason,
                    'subcultured_at' => $s->subcultured_at->toIso8601String(),
                    'readable_at' => $s->subcultured_at->addHours($isolate->culture->sampleStudy->protocolVersion->first_read_min_hours)->toIso8601String(),
                    'result' => $s->result?->label(),
                    'notes' => $s->notes,
                    'read_at' => $s->read_at?->toIso8601String(),
                ])->values(),
            'referrals' => Referral::query()->where('isolate_id', $isolate->id)->orderBy('sent_at')->get()
                ->map(fn (Referral $r) => [
                    'id' => $r->id,
                    'destination' => $r->destination,
                    'reason' => $r->reason,
                    'sent_at' => $r->sent_at->toIso8601String(),
                    'result' => $r->result,
                    'result_at' => $r->result_at?->toIso8601String(),
                ])->values(),
            'susceptibility' => $test === null ? null : [
                'status' => $test->status->label(),
                'panel' => $test->panel->name,
                'set_up_at' => $test->set_up_at->toIso8601String(),
            ],
            'allowPresumptive' => (bool) $isolate->culture->sampleStudy->protocolVersion->allow_presumptive_ast,
            'vitekPanels' => $panels->forSample($sampleTypeId)->filter(fn (AstPanel $p) => $p->is_vitek)
                ->map(fn (AstPanel $p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'organisms' => Organism::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Organism $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'group' => $o->organism_group->value,
                    'group_label' => $o->organism_group->label(),
                    'has_panel' => $panelGroups->contains($o->organism_group->value),
                ])->values(),
            'methods' => array_map(fn (IdentificationMethod $m) => ['value' => $m->value, 'label' => $m->label()], IdentificationMethod::cases()),
            'media' => Medium::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'atmospheres' => array_map(fn (Atmosphere $a) => ['value' => $a->value, 'label' => $a->label()], Atmosphere::cases()),
            'significances' => array_map(fn (IsolateSignificance $s) => ['value' => $s->value, 'label' => $s->label()], IsolateSignificance::cases()),
            'subcultureResults' => array_map(fn (SubcultureResult $r) => ['value' => $r->value, 'label' => $r->label()], SubcultureResult::cases()),
            'can' => [
                'identify' => $user?->can(Permission::IdentificationPerform->value) ?? false,
                'setup_tests' => ($user?->can(Permission::IdentificationBiochemSetup->value) || $user?->can(Permission::IdentificationPerform->value)),
                'read_tests' => ($user?->can(Permission::IdentificationBiochemRead->value) || $user?->can(Permission::IdentificationPerform->value)),
                'vitek' => ($user?->can(Permission::VitekLoad->value) || $user?->can(Permission::IdentificationPerform->value)),
                'susceptibility' => $user?->can(Permission::AstSetup->value) ?? false,
            ],
        ]);
    }

    public function store(Request $request, Isolate $isolate, IdentifyIsolate $identify): RedirectResponse
    {
        $this->authorizeFor($request, $isolate);

        $validated = $request->validate([
            'organism_id' => ['nullable', 'required_unless:level,inconclusive', 'integer', Rule::exists('organisms', 'id')->where('is_active', true)],
            'method' => ['required', Rule::enum(IdentificationMethod::class)],
            'level' => ['required', Rule::enum(IdentificationLevel::class)],
            'tests' => ['nullable', 'string', 'max:2000'],
            'confidence' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'requires_susceptibility' => ['required', 'boolean'],
            'no_ast_reason' => ['nullable', 'string', 'max:500'],
        ], [], ['organism_id' => 'organismo', 'method' => 'método', 'no_ast_reason' => 'motivo', 'notes' => 'motivo']);

        $level = IdentificationLevel::from($validated['level']);
        $identify->handle(
            $this->user($request),
            $isolate,
            isset($validated['organism_id']) ? (int) $validated['organism_id'] : null,
            IdentificationMethod::from($validated['method']),
            $level,
            $validated['tests'] ?? null,
            $validated['confidence'] ?? null,
            (bool) $validated['requires_susceptibility'],
            $validated['no_ast_reason'] ?? null,
            $validated['notes'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($level) {
            IdentificationLevel::Definitive => 'Identificación definitiva registrada.',
            IdentificationLevel::Preliminary => 'Identificación preliminar registrada.',
            IdentificationLevel::Inconclusive => 'Identificación no concluyente registrada. La identificación sigue pendiente.',
        }]);

        return $level === IdentificationLevel::Definitive ? to_route('workbenches.show', 'identification') : back();
    }

    public function storeTests(Request $request, Isolate $isolate, RecordIsolateTests $record): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'tests' => ['required', 'array', 'min:1', 'max:30'],
            'tests.*.test_id' => ['required', 'integer'],
            'tests.*.result_code' => ['nullable', 'string', 'max:20'],
            'tests.*.notes' => ['nullable', 'string', 'max:500'],
        ], [], ['tests' => 'pruebas']);

        $created = $record->handle($this->user($request), $isolate, array_map(fn (array $t) => new IsolateTestInput(
            testId: (int) $t['test_id'],
            resultCode: filled($t['result_code'] ?? null) ? $t['result_code'] : null,
            notes: $t['notes'] ?? null,
        ), array_values($validated['tests'])));

        $pending = $created->whereNull('read_at')->count();
        Inertia::flash('toast', ['type' => 'success', 'message' => $pending > 0
            ? "Pruebas registradas. {$pending} quedan sembradas para leer."
            : 'Pruebas registradas.']);

        return back();
    }

    public function readTests(Request $request, Isolate $isolate, ReadIsolateTests $read): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*.id' => ['required', 'integer'],
            'results.*.result_code' => ['required', 'string', 'max:20'],
            'results.*.notes' => ['nullable', 'string', 'max:500'],
        ], [], ['results.*.result_code' => 'resultado']);

        /** @var list<array{id: int, result_code: string, notes?: string|null}> $list */
        $list = array_values($validated['results']);
        $rows = collect($list);
        $read->handle(
            $this->user($request),
            $isolate,
            $rows->mapWithKeys(fn (array $r) => [(int) $r['id'] => (string) $r['result_code']])->all(),
            $rows->mapWithKeys(fn (array $r) => [(int) $r['id'] => $r['notes'] ?? null])->all(),
            EarlyConfirmation::fromRequest($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lectura de pruebas registrada.']);

        return back();
    }

    public function significance(Request $request, Isolate $isolate, ChangeIsolateSignificance $change): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'significance' => ['required', Rule::enum(IsolateSignificance::class)],
            'significance_reason' => ['required', 'string', 'max:500'],
        ], [], ['significance_reason' => 'motivo']);

        $isolate = $change->handle($this->user($request), $isolate, IsolateSignificance::from($validated['significance']), $validated['significance_reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Aislamiento {$isolate->number}: {$isolate->significance->label()}."]);

        return back();
    }

    public function subculture(Request $request, Isolate $isolate, SubcultureIsolate $subculture): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'medium_id' => ['required', 'integer'],
            'incubation_temperature' => ['required', 'string', 'max:30'],
            'atmosphere' => ['required', Rule::enum(Atmosphere::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['medium_id' => 'medio']);

        $subculture->handle($this->user($request), $isolate, (int) $validated['medium_id'], $validated['incubation_temperature'], Atmosphere::from($validated['atmosphere']), $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Repique registrado. La lectura queda programada.']);

        return back();
    }

    public function readSubculture(Request $request, IsolateSubculture $subculture, ReadIsolateSubculture $read): RedirectResponse
    {
        $this->authorizeSite($subculture->isolate);
        $validated = $request->validate([
            'result' => ['required', Rule::enum(SubcultureResult::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $read->handle($this->user($request), $subculture, SubcultureResult::from($validated['result']), $validated['notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lectura del repique registrada.']);

        return back();
    }

    public function loadInstrument(Request $request, Isolate $isolate, LoadInstrument $load): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'instrument' => ['required', 'string', 'max:50'],
            'id_card_type' => ['nullable', 'string', 'max:30'],
            'id_card_number' => ['nullable', 'string', 'max:50'],
            'ast_card_type' => ['nullable', 'string', 'max:30'],
            'ast_card_number' => ['nullable', 'string', 'max:50'],
            'ast_panel_id' => ['nullable', 'integer'],
            'cassette' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:10'],
            'suspension' => ['nullable', 'string', 'max:50'],
            'loaded_at' => ['nullable', 'date'],
        ], [], ['instrument' => 'equipo']);

        $load->handle($this->user($request), $isolate, new InstrumentLoadData(
            instrument: $validated['instrument'],
            idCardType: $validated['id_card_type'] ?? null,
            idCardNumber: $validated['id_card_number'] ?? null,
            astCardType: $validated['ast_card_type'] ?? null,
            astCardNumber: $validated['ast_card_number'] ?? null,
            cassette: $validated['cassette'] ?? null,
            position: $validated['position'] ?? null,
            suspension: $validated['suspension'] ?? null,
            loadedAt: isset($validated['loaded_at']) ? Date::parse($validated['loaded_at']) : null,
            astPanelId: isset($validated['ast_panel_id']) ? (int) $validated['ast_panel_id'] : null,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Carga en el equipo registrada. El resultado queda pendiente.']);

        return back();
    }

    public function refer(Request $request, Isolate $isolate, ReferIsolate $refer): RedirectResponse
    {
        $this->authorizeSite($isolate);
        $validated = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
            'sent_at' => ['nullable', 'date'],
        ], [], ['destination' => 'destino', 'reason' => 'motivo']);

        $refer->handle($this->user($request), $isolate, $validated['destination'], $validated['reason'], isset($validated['sent_at']) ? Date::parse($validated['sent_at']) : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Derivación registrada. El resultado queda pendiente.']);

        return back();
    }

    public function referralResult(Request $request, Referral $referral, RecordReferralResult $record): RedirectResponse
    {
        $this->authorizeSite($referral->isolate);
        $validated = $request->validate(['result' => ['required', 'string', 'max:2000']], [], ['result' => 'resultado']);

        $record->handle($this->user($request), $referral, $validated['result']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resultado de la derivación registrado.']);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function authorizeFor(Request $request, Isolate $isolate): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can(Permission::IdentificationPerform->value) || $user?->can(Permission::IdentificationBiochemRead->value) || $user?->can(Permission::IdentificationBiochemSetup->value),
            403,
        );
        $this->authorizeSite($isolate);
    }

    private function authorizeSite(Isolate $isolate): void
    {
        abort_unless($isolate->culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
