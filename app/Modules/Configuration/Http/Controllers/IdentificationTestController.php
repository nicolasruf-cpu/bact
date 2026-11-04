<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\SaveIdentificationTest;
use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de pruebas de identificación (Gram, rápidas, bioquímicas, complementarias y de
 * mecanismo). Lo ve quien prepara la configuración clínica y lo define el Director técnico.
 */
class IdentificationTestController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/identification-tests/Index', [
            'tests' => IdentificationTest::query()->orderByDesc('is_active')->orderBy('kind')->orderBy('name')->get()
                ->map(fn (IdentificationTest $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'kind' => $t->kind->value,
                    'kind_label' => $t->kind->label(),
                    'result_options' => $t->result_options,
                    'incubation_hours' => $t->incubation_hours,
                    'is_reportable' => $t->is_reportable,
                    'is_active' => $t->is_active,
                    'is_reference_template' => $t->is_reference_template,
                    'lock_version' => $t->lock_version,
                ])->values(),
            'kinds' => array_map(fn (IdentificationTestKind $k) => ['value' => $k->value, 'label' => $k->label()], IdentificationTestKind::cases()),
            'can' => ['edit' => $request->user()?->can(Permission::CLINICAL_APPROVE) ?? false],
        ]);
    }

    public function store(Request $request, SaveIdentificationTest $save): RedirectResponse
    {
        $test = $save->handle($this->user($request), null, $this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Prueba {$test->code} creada."]);

        return back();
    }

    public function update(Request $request, IdentificationTest $test, SaveIdentificationTest $save): RedirectResponse
    {
        $lockVersion = $request->validate(['lock_version' => ['required', 'integer']])['lock_version'];
        $save->handle($this->user($request), $test->id, $this->data($request), (int) $lockVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Prueba {$test->code} guardada."]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array{code: string, name: string, kind: string, incubation_hours: int|null, is_reportable: bool, is_active: bool, result_options: list<array{code: string, label: string}>}
     */
    private function data(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string'],
            'incubation_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'is_reportable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'result_options' => ['present', 'array', 'max:30'],
            'result_options.*.code' => ['nullable', 'string', 'max:20'],
            'result_options.*.label' => ['required', 'string', 'max:255'],
        ], [], ['code' => 'código', 'name' => 'nombre', 'result_options.*.label' => 'resultado']);

        /** @var list<array{code?: string|null, label: string}> $options */
        $options = array_values($validated['result_options']);

        return [
            'code' => (string) ($validated['code'] ?? ''),
            'name' => (string) $validated['name'],
            'kind' => (string) $validated['kind'],
            'incubation_hours' => isset($validated['incubation_hours']) ? (int) $validated['incubation_hours'] : null,
            'is_reportable' => (bool) $validated['is_reportable'],
            'is_active' => (bool) $validated['is_active'],
            'result_options' => array_map(fn (array $o) => ['code' => (string) ($o['code'] ?? ''), 'label' => (string) $o['label']], $options),
        ];
    }
}
