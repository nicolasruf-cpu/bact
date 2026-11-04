<?php

namespace App\Modules\Configuration\Queries;

use App\Models\User;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Identity\Enums\Permission;

class AstPanelAdminQuery
{
    /**
     * @return array<string, mixed>
     */
    public function index(User $user): array
    {
        return [
            'panels' => AstPanel::query()
                ->with(['antibiotics', 'sampleTypes'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (AstPanel $panel) => [
                    'id' => $panel->id,
                    'code' => $panel->code,
                    'name' => $panel->name,
                    'is_vitek' => $panel->is_vitek,
                    'read_min_hours' => $panel->read_min_hours,
                    'read_due_hours' => $panel->read_due_hours,
                    'is_active' => $panel->is_active,
                    'is_reference_template' => $panel->is_reference_template,
                    'organism_groups' => $panel->organism_groups->map(fn (OrganismGroup $g) => $g->value)->values()->all(),
                    'sample_type_ids' => $panel->sampleTypes->pluck('id')->values()->all(),
                    'antibiotic_ids' => $panel->antibiotics->pluck('id')->values()->all(),
                    'lock_version' => $panel->lock_version,
                ])
                ->values()
                ->all(),
            'organismGroups' => array_map(
                fn (OrganismGroup $g) => ['value' => $g->value, 'label' => $g->label()],
                OrganismGroup::cases(),
            ),
            'sampleTypes' => SampleType::query()->orderBy('name')->get()
                ->map(fn (SampleType $s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'is_active' => $s->is_active])
                ->values()->all(),
            'antibiotics' => Antibiotic::query()->orderBy('name')->get()
                ->map(fn (Antibiotic $a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->displayName(), 'is_active' => (bool) $a->is_active])
                ->values()->all(),
            'can' => ['edit' => $user->can(Permission::CLINICAL_APPROVE)],
        ];
    }
}
