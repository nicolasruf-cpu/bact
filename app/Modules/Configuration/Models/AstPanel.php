<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Panel de antibiograma por grupo de organismo.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OrganismGroup $organism_group
 * @property bool $is_reference_template
 * @property bool $is_active
 * @property-read Collection<int, Antibiotic> $antibiotics
 */
class AstPanel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'organism_group' => OrganismGroup::class,
            'is_reference_template' => 'boolean',
            'is_active' => 'boolean',
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
}
