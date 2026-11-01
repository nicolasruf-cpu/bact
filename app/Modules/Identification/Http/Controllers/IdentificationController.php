<?php

namespace App\Modules\Identification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IdentificationController extends Controller
{
    public function create(Request $request, Isolate $isolate, SampleHeader $header): Response
    {
        $this->authorizeFor($request, $isolate);
        $isolate->load(['culture.sampleStudy', 'plate.medium']);
        $panelGroups = AstPanel::query()->where('is_active', true)->pluck('organism_group')->map(fn ($g) => $g->value)->unique()->values();

        return Inertia::render('identification/Identify', [
            'sample' => $header->for($isolate->culture->sampleStudy),
            'isolate' => [
                'ulid' => $isolate->ulid,
                'number' => $isolate->number,
                'morphotype' => $isolate->morphotype,
                'count_category' => $isolate->count_category,
                'plate' => $isolate->plate?->medium->name,
                'status' => $isolate->status->value,
            ],
            'history' => Identification::query()->where('isolate_id', $isolate->id)->with('organism')->orderBy('id')->get()
                ->map(fn (Identification $i) => [
                    'organism' => $i->organism->name,
                    'method' => $i->method->label(),
                    'level' => $i->level->label(),
                    'tests' => $i->tests,
                    'identified_at' => $i->identified_at->toIso8601String(),
                ])->values(),
            'organisms' => Organism::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Organism $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'group' => $o->organism_group->value,
                    'group_label' => $o->organism_group->label(),
                    'has_panel' => $panelGroups->contains($o->organism_group->value),
                ])->values(),
            'methods' => array_map(fn (IdentificationMethod $m) => ['value' => $m->value, 'label' => $m->label()], IdentificationMethod::cases()),
        ]);
    }

    public function store(Request $request, Isolate $isolate, IdentifyIsolate $identify): RedirectResponse
    {
        $this->authorizeFor($request, $isolate);

        $validated = $request->validate([
            'organism_id' => ['required', 'integer', Rule::exists('organisms', 'id')->where('is_active', true)],
            'method' => ['required', Rule::enum(IdentificationMethod::class)],
            'level' => ['required', Rule::enum(IdentificationLevel::class)],
            'tests' => ['nullable', 'string', 'max:2000'],
            'confidence' => ['nullable', 'string', 'max:30'],
            'requires_susceptibility' => ['required', 'boolean'],
            'no_ast_reason' => ['nullable', 'string', 'max:500'],
        ], [], ['organism_id' => 'organismo', 'method' => 'método', 'no_ast_reason' => 'motivo']);

        /** @var User $user */
        $user = $request->user();
        $level = IdentificationLevel::from($validated['level']);
        $identify->handle(
            $user,
            $isolate,
            (int) $validated['organism_id'],
            IdentificationMethod::from($validated['method']),
            $level,
            $validated['tests'] ?? null,
            $validated['confidence'] ?? null,
            (bool) $validated['requires_susceptibility'],
            $validated['no_ast_reason'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $level === IdentificationLevel::Definitive ? 'Identificación definitiva registrada.' : 'Identificación preliminar registrada.']);

        return $level === IdentificationLevel::Definitive ? to_route('workbenches.show', 'identification') : back();
    }

    private function authorizeFor(Request $request, Isolate $isolate): void
    {
        abort_unless($request->user()?->can(Permission::IdentificationPerform->value), 403);
        abort_unless($isolate->culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
