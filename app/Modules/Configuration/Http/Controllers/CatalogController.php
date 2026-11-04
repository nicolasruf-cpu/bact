<?php

namespace App\Modules\Configuration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Actions\SaveCatalogEntry;
use App\Modules\Configuration\Enums\CatalogType;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Queries\CatalogQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogos generales (config.catalogs.edit): una pantalla por catálogo.
 */
class CatalogController extends Controller
{
    public function index(Request $request, CatalogQuery $query, string $catalog = 'sample-types'): Response
    {
        $type = CatalogType::tryFrom($catalog);
        abort_if($type === null, 404);
        $search = $request->string('search')->trim()->toString() ?: null;

        return Inertia::render('admin/catalogs/Index', [
            'catalog' => [
                'value' => $type->value,
                'label' => $type->label(),
                'singular' => $type->singular(),
                'has_organism_group' => $type->hasOrganismGroup(),
                'has_antibiotic_fields' => $type->hasAntibioticFields(),
            ],
            'antibiotics' => $type->hasAntibioticFields()
                ? Antibiotic::query()->orderBy('name')->get(['id', 'code', 'name'])->values()
                : [],
            'types' => $query->types(),
            'entries' => $query->list($type, $search),
            'filters' => ['search' => $search],
            'organismGroups' => array_map(
                fn (OrganismGroup $g) => ['value' => $g->value, 'label' => $g->label()],
                OrganismGroup::cases(),
            ),
        ]);
    }

    public function store(Request $request, SaveCatalogEntry $save, string $catalog): RedirectResponse
    {
        $type = CatalogType::tryFrom($catalog);
        abort_if($type === null, 404);

        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'code' => ['required_without:id', 'nullable', 'string', 'max:'.$type->codeMaxLength()],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'organism_group' => ['nullable', 'string'],
            'disk_content' => ['nullable', 'string', 'max:20'],
            'reported_as_antibiotic_id' => ['nullable', 'integer'],
            'lock_version' => ['nullable', 'integer'],
        ], [], ['code' => 'código', 'name' => 'nombre', 'organism_group' => 'grupo']);

        /** @var User $user */
        $user = $request->user();
        $entry = $save->handle(
            $user,
            $type,
            isset($validated['id']) ? (int) $validated['id'] : null,
            [
                'code' => (string) ($validated['code'] ?? ''),
                'name' => (string) $validated['name'],
                'is_active' => (bool) $validated['is_active'],
                'organism_group' => $validated['organism_group'] ?? null,
                'disk_content' => $validated['disk_content'] ?? null,
                'reported_as_antibiotic_id' => isset($validated['reported_as_antibiotic_id']) ? (int) $validated['reported_as_antibiotic_id'] : null,
            ],
            isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($type->singular()).' '.$entry->getAttribute('code').' guardado.']);

        return back();
    }
}
