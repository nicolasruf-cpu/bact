<?php

namespace App\Modules\Configuration\Queries;

use App\Modules\Configuration\Enums\CatalogType;
use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Model;

class CatalogQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(CatalogType $type, ?string $search): array
    {
        $class = $type->modelClass();

        return $class::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%')))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Model $entry) use ($type) {
                $group = $type->hasOrganismGroup() ? $entry->getAttribute('organism_group') : null;

                return [
                    'id' => (int) $entry->getKey(),
                    'code' => (string) $entry->getAttribute('code'),
                    'name' => (string) $entry->getAttribute('name'),
                    'is_active' => (bool) $entry->getAttribute('is_active'),
                    'organism_group' => $group instanceof OrganismGroup ? $group->label() : null,
                    'disk_content' => $type->hasAntibioticFields() ? $entry->getAttribute('disk_content') : null,
                    'reported_as_antibiotic_id' => $type->hasAntibioticFields() ? $entry->getAttribute('reported_as_antibiotic_id') : null,
                    'lock_version' => (int) $entry->getAttribute('lock_version'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public function types(): array
    {
        return array_map(fn (CatalogType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'count' => $type->modelClass()::query()->count(),
        ], CatalogType::cases());
    }
}
