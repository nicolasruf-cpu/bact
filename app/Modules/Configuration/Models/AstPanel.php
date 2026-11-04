<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Panel de antibiograma (D-0021): vale para uno o más grupos de germen y para una o más
 * muestras (sin muestras = todas). Un panel VITEK es un panel más, con la marca.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property SupportCollection<int, OrganismGroup> $organism_groups
 * @property bool $is_vitek
 * @property int|null $read_min_hours incubación propia del panel (null = la del protocolo)
 * @property int|null $read_due_hours
 * @property bool $is_reference_template
 * @property bool $is_active
 * @property int $lock_version
 * @property-read Collection<int, Antibiotic> $antibiotics
 * @property-read Collection<int, SampleType> $sampleTypes
 */
class AstPanel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'organism_groups' => AsEnumCollection::of(OrganismGroup::class),
            'is_vitek' => 'boolean',
            'read_min_hours' => 'integer',
            'read_due_hours' => 'integer',
            'is_reference_template' => 'boolean',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Antibiotic, $this>
     */
    public function antibiotics(): BelongsToMany
    {
        return $this->belongsToMany(Antibiotic::class, 'ast_panel_antibiotics')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @return BelongsToMany<SampleType, $this>
     */
    public function sampleTypes(): BelongsToMany
    {
        return $this->belongsToMany(SampleType::class, 'ast_panel_sample_types');
    }

    /**
     * @param  Builder<AstPanel>  $query
     */
    public function scopeForGroup(Builder $query, OrganismGroup $group): void
    {
        $query->whereJsonContains('organism_groups', $group->value);
    }

    /**
     * Paneles que valen para la muestra: los que la incluyen y los que no restringen muestras.
     *
     * @param  Builder<AstPanel>  $query
     */
    public function scopeForSampleType(Builder $query, int $sampleTypeId): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('sampleTypes')
            ->orWhereHas('sampleTypes', fn (Builder $q) => $q->whereKey($sampleTypeId)));
    }

    public function appliesToAllSamples(): bool
    {
        return $this->sampleTypes->isEmpty();
    }
}
