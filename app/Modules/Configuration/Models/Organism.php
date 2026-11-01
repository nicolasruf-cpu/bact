<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OrganismGroup $organism_group
 * @property bool $is_active
 */
class Organism extends Model
{
    protected $table = 'organisms';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'organism_group' => OrganismGroup::class];
    }
}
