<?php

namespace App\Modules\Cultures\Models;

use App\Models\User;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Examen directo de la muestra (Gram, fresco…), uno por estudio. Las opciones salen
 * de la versión de protocolo congelada.
 *
 * @property int $id
 * @property string $ulid
 * @property int $sample_study_id
 * @property array<string, list<string>> $results
 * @property string|null $description
 * @property bool $organisms_seen
 * @property int $performed_by
 * @property Carbon $performed_at
 * @property int $lock_version
 * @property-read SampleStudy $sampleStudy
 * @property-read User $performer
 */
class DirectExam extends Model
{
    use HasUlids;

    protected $guarded = ['id', 'ulid'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'organisms_seen' => 'boolean',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SampleStudy, $this>
     */
    public function sampleStudy(): BelongsTo
    {
        return $this->belongsTo(SampleStudy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
