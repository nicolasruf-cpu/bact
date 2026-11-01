<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $breakpoint_set_id
 * @property OrganismGroup $organism_group
 * @property int $antibiotic_id
 * @property AstMethod $method
 * @property string $susceptible_value
 * @property string $resistant_value
 */
class Breakpoint extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'organism_group' => OrganismGroup::class,
            'method' => AstMethod::class,
        ];
    }
}
