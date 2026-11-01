<?php

namespace App\Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class Antibiotic extends Model
{
    protected $table = 'antibiotics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
