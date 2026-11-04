<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\SaveAstPanel;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Queries\AstPanelAdminQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paneles de antibiograma (D-0021): los ve quien prepara la configuración clínica y los
 * define el Director técnico responsable.
 */
class AstPanelController extends Controller
{
    public function index(Request $request, AstPanelAdminQuery $query): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/ast-panels/Index', $query->index($user));
    }

    public function store(Request $request, SaveAstPanel $save): RedirectResponse
    {
        $panel = $save->handle($this->user($request), null, $this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Panel {$panel->code} creado."]);

        return back();
    }

    public function update(Request $request, AstPanel $panel, SaveAstPanel $save): RedirectResponse
    {
        $lockVersion = $request->validate(['lock_version' => ['required', 'integer']])['lock_version'];
        $save->handle($this->user($request), $panel->id, $this->data($request), (int) $lockVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Panel {$panel->code} guardado."]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array{code: string, name: string, is_vitek: bool, is_active: bool, organism_groups: list<string>, sample_type_ids: list<int>, antibiotic_ids: list<int>, read_min_hours: int|null, read_due_hours: int|null}
     */
    private function data(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'is_vitek' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'organism_groups' => ['present', 'array'],
            'organism_groups.*' => ['string'],
            'sample_type_ids' => ['present', 'array'],
            'sample_type_ids.*' => ['integer'],
            'antibiotic_ids' => ['present', 'array'],
            'antibiotic_ids.*' => ['integer'],
            'read_min_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'read_due_hours' => ['nullable', 'integer', 'min:0', 'max:2000'],
        ], [], ['code' => 'código', 'name' => 'nombre']);

        return [
            'code' => (string) ($validated['code'] ?? ''),
            'name' => (string) $validated['name'],
            'is_vitek' => (bool) $validated['is_vitek'],
            'is_active' => (bool) $validated['is_active'],
            'organism_groups' => array_values(array_map('strval', $validated['organism_groups'])),
            'sample_type_ids' => array_values(array_map('intval', $validated['sample_type_ids'])),
            'antibiotic_ids' => array_values(array_map('intval', $validated['antibiotic_ids'])),
            'read_min_hours' => isset($validated['read_min_hours']) ? (int) $validated['read_min_hours'] : null,
            'read_due_hours' => isset($validated['read_due_hours']) ? (int) $validated['read_due_hours'] : null,
        ];
    }
}
