<?php

namespace App\Modules\Configuration\Queries;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Breakpoint;
use App\Modules\Configuration\Models\BreakpointSet;
use Illuminate\Support\Facades\DB;

class BreakpointAdminQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return BreakpointSet::query()->withCount('breakpoints')->orderByDesc('id')->get()
            ->map(fn (BreakpointSet $set) => [
                'id' => $set->id,
                'name' => $set->name,
                'edition' => $set->edition,
                'status' => $set->status->value,
                'status_label' => $set->status->label(),
                'is_synthetic' => $set->is_synthetic,
                'breakpoints_count' => (int) $set->getAttribute('breakpoints_count'),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(BreakpointSet $set): array
    {
        $users = DB::table('users')->whereIn('id', array_filter([$set->created_by, $set->approved_by]))->pluck('name', 'id');

        return [
            'id' => $set->id,
            'name' => $set->name,
            'edition' => $set->edition,
            'notes' => $set->notes,
            'status' => $set->status->value,
            'status_label' => $set->status->label(),
            'is_synthetic' => $set->is_synthetic,
            'lock_version' => $set->lock_version,
            'created_by' => $set->created_by ? ($users[$set->created_by] ?? null) : null,
            'approved_by' => $set->approved_by ? ($users[$set->approved_by] ?? null) : null,
            'approved_at' => $set->approved_at?->toIso8601String(),
            'activated_at' => $set->activated_at?->toIso8601String(),
            'retired_at' => $set->retired_at?->toIso8601String(),
            'tests_count' => DB::table('susceptibility_tests')->where('breakpoint_set_id', $set->id)->count(),
            'breakpoints' => $set->breakpoints()->with('antibiotic')->get()
                ->sortBy(fn (Breakpoint $bp) => $bp->organism_group->value.' '.$bp->antibiotic->name.' '.$bp->method->value)
                ->map(fn (Breakpoint $bp) => [
                    'organism_group' => $bp->organism_group->value,
                    'antibiotic_code' => $bp->antibiotic->code,
                    'antibiotic' => $bp->antibiotic->name,
                    'method' => $bp->method->value,
                    'susceptible' => (string) (float) $bp->susceptible_value,
                    'resistant' => (string) (float) $bp->resistant_value,
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'groups' => array_map(fn (OrganismGroup $g) => ['value' => $g->value, 'label' => $g->label()], OrganismGroup::cases()),
            'methods' => array_map(fn (AstMethod $m) => ['value' => $m->value, 'label' => $m->label()], AstMethod::cases()),
            'antibiotics' => DB::table('antibiotics')->where('is_active', true)->orderBy('name')->get(['code', 'name'])
                ->map(fn ($a) => ['code' => (string) $a->code, 'name' => (string) $a->name])->values()->all(),
        ];
    }
}
