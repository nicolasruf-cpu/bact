<?php

namespace App\Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $disk_content carga del disco (p. ej., «10 µg»)
 * @property int|null $reported_as_antibiotic_id prueba subrogante: se informa como este antibiótico
 * @property-read Antibiotic|null $reportedAs
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

    /**
     * @return BelongsTo<Antibiotic, $this>
     */
    public function reportedAs(): BelongsTo
    {
        return $this->belongsTo(Antibiotic::class, 'reported_as_antibiotic_id');
    }

    /**
     * Nombre con la carga del disco, para distinguir, p. ej., gentamicina 10 µg y 120 µg.
     */
    public function displayName(): string
    {
        return $this->disk_content ? "{$this->name} {$this->disk_content}" : $this->name;
    }
}
