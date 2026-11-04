<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\AstPanel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Paneles de antibiograma que valen para un germen (por su grupo) en una muestra (D-0021).
 * Primero los que nombran la muestra, después los que valen para todas; el primero es el
 * que se propone y el bioquímico puede elegir otro de la lista.
 */
class AstPanelResolver
{
    /**
     * @return Collection<int, AstPanel>
     */
    public function candidates(OrganismGroup $group, int $sampleTypeId): Collection
    {
        return AstPanel::query()
            ->where('is_active', true)
            ->forGroup($group)
            ->forSampleType($sampleTypeId)
            ->withCount('sampleTypes')
            ->orderByRaw('sample_types_count = 0')
            ->orderBy('name')
            ->get();
    }

    /**
     * Todos los paneles activos de la muestra, sin saber todavía el germen (antibiograma con
     * identificación presuntiva, p. ej. la tarjeta del VITEK). Los VITEK primero.
     *
     * @return Collection<int, AstPanel>
     */
    public function forSample(int $sampleTypeId): Collection
    {
        return AstPanel::query()
            ->where('is_active', true)
            ->forSampleType($sampleTypeId)
            ->withCount('sampleTypes')
            ->orderByDesc('is_vitek')
            ->orderByRaw('sample_types_count = 0')
            ->orderBy('name')
            ->get();
    }

    public function has(OrganismGroup $group, int $sampleTypeId): bool
    {
        return AstPanel::query()
            ->where('is_active', true)
            ->forGroup($group)
            ->forSampleType($sampleTypeId)
            ->exists();
    }

    /**
     * Grupos con al menos un panel activo para la muestra.
     *
     * @return array<int, string>
     */
    public function groupsWithPanel(int $sampleTypeId): array
    {
        return AstPanel::query()
            ->where('is_active', true)
            ->forSampleType($sampleTypeId)
            ->get()
            ->flatMap(fn (AstPanel $panel) => $panel->organism_groups->map(fn (OrganismGroup $g) => $g->value))
            ->unique()
            ->values()
            ->all();
    }
}
