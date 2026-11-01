<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $breakpoint_set_id
 * @property OrganismGroup $organism_group
 * @property int $antibiotic_id
 * @property AstMethod $method
 * @property string $susceptible_value
 * @property string $resistant_value
 * @property-read Antibiotic $antibiotic
 */
class Breakpoint extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Antibiotic, $this>
     */
    public function antibiotic(): BelongsTo
    {
        return $this->belongsTo(Antibiotic::class);
    }

    protected function casts(): array
    {
        return [
            'organism_group' => OrganismGroup::class,
            'method' => AstMethod::class,
        ];
    }
}
