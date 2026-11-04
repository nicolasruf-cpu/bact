<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\SaveAstRule;
use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reglas de antibiograma: informe selectivo, comentarios y reglas expertas (revisión de
 * marchas C2). Las define el Director técnico; el sistema no trae reglas propias.
 */
class AstRuleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/ast-rules/Index', [
            'rules' => AstRule::query()->with(['antibiotics', 'sampleTypes', 'test'])->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (AstRule $r) => [
                    'id' => $r->id,
                    'code' => $r->code,
                    'name' => $r->name,
                    'effect' => $r->effect->value,
                    'effect_label' => $r->effect->label(),
                    'interpretation' => $r->interpretation?->value,
                    'comment' => $r->comment,
                    'organism_groups' => $r->organism_groups->map(fn (OrganismGroup $g) => $g->value)->values()->all(),
                    'sample_type_ids' => $r->sampleTypes->pluck('id')->values()->all(),
                    'antibiotic_ids' => $r->antibiotics->pluck('id')->values()->all(),
                    'identification_test_id' => $r->identification_test_id,
                    'test_result_code' => $r->test_result_code,
                    'is_active' => $r->is_active,
                    'lock_version' => $r->lock_version,
                ])->values(),
            'effects' => array_map(fn (AstRuleEffect $e) => ['value' => $e->value, 'label' => $e->label()], AstRuleEffect::cases()),
            'interpretations' => array_map(fn (Interpretation $i) => ['value' => $i->value, 'label' => $i->label()], Interpretation::cases()),
            'organismGroups' => array_map(fn (OrganismGroup $g) => ['value' => $g->value, 'label' => $g->label()], OrganismGroup::cases()),
            'sampleTypes' => SampleType::query()->orderBy('name')->get(['id', 'name']),
            'antibiotics' => Antibiotic::query()->orderBy('name')->get()->map(fn (Antibiotic $a) => ['id' => $a->id, 'name' => $a->displayName()])->values(),
            'tests' => IdentificationTest::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (IdentificationTest $t) => ['id' => $t->id, 'name' => $t->name, 'options' => $t->result_options])->values(),
            'can' => ['edit' => $request->user()?->can(Permission::CLINICAL_APPROVE) ?? false],
        ]);
    }

    public function store(Request $request, SaveAstRule $save): RedirectResponse
    {
        $rule = $save->handle($this->user($request), null, $this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Regla {$rule->code} creada."]);

        return back();
    }

    public function update(Request $request, AstRule $rule, SaveAstRule $save): RedirectResponse
    {
        $lockVersion = $request->validate(['lock_version' => ['required', 'integer']])['lock_version'];
        $save->handle($this->user($request), $rule->id, $this->data($request), (int) $lockVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Regla {$rule->code} guardada."]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array{code: string, name: string, effect: string, interpretation: string|null, comment: string|null, organism_groups: list<string>, sample_type_ids: list<int>, antibiotic_ids: list<int>, identification_test_id: int|null, test_result_code: string|null, is_active: bool}
     */
    private function data(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'effect' => ['required', 'string'],
            'interpretation' => ['nullable', 'string'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'organism_groups' => ['present', 'array'],
            'organism_groups.*' => ['string'],
            'sample_type_ids' => ['present', 'array'],
            'sample_type_ids.*' => ['integer'],
            'antibiotic_ids' => ['present', 'array'],
            'antibiotic_ids.*' => ['integer'],
            'identification_test_id' => ['nullable', 'integer'],
            'test_result_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ], [], ['code' => 'código', 'name' => 'nombre']);

        return [
            'code' => (string) ($validated['code'] ?? ''),
            'name' => (string) $validated['name'],
            'effect' => (string) $validated['effect'],
            'interpretation' => $validated['interpretation'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'organism_groups' => array_values(array_map('strval', $validated['organism_groups'])),
            'sample_type_ids' => array_values(array_map('intval', $validated['sample_type_ids'])),
            'antibiotic_ids' => array_values(array_map('intval', $validated['antibiotic_ids'])),
            'identification_test_id' => isset($validated['identification_test_id']) ? (int) $validated['identification_test_id'] : null,
            'test_result_code' => $validated['test_result_code'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
