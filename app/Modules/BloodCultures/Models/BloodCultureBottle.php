<?php

namespace App\Modules\BloodCultures\Models;

use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Enums\PositivitySource;
use App\Modules\Configuration\Data\BloodCultureSettings;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Frasco de hemocultivo o retrocultivo: uno por muestra.
 *
 * @property int $id
 * @property string $ulid
 * @property int $sample_study_id
 * @property BottleMethod $method
 * @property string $bottle_type
 * @property string|null $instrument
 * @property string|null $instrument_position
 * @property Carbon $loaded_at
 * @property int $loaded_by
 * @property Carbon $incubation_ends_at
 * @property BottleStatus $status
 * @property Carbon|null $positive_at
 * @property PositivitySource|null $positivity_source
 * @property int|null $positive_recorded_by
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $notes
 * @property int $lock_version
 * @property-read SampleStudy $sampleStudy
 * @property-read Collection<int, BlindSubculture> $blindSubcultures
 * @property-read GramStain|null $gramStain
 */
class BloodCultureBottle extends Model
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
            'method' => BottleMethod::class,
            'status' => BottleStatus::class,
            'positivity_source' => PositivitySource::class,
            'loaded_at' => 'datetime',
            'incubation_ends_at' => 'datetime',
            'positive_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isAutomated(): bool
    {
        return $this->method === BottleMethod::Automated;
    }

    public function settings(): BloodCultureSettings
    {
        return $this->sampleStudy->protocolVersion->bloodCultureSettings()
            ?? throw new LogicException('La versión de protocolo no tiene parámetros de hemocultivo.');
    }

    public function incubationDays(): int
    {
        return $this->settings()->incubationDays($this->isAutomated());
    }

    /**
     * Tiempo hasta la positividad en minutos desde la carga. No aplica si el positivo salió
     * de un repique (ahí solo se sabe el día).
     */
    public function minutesToPositivity(): ?int
    {
        if ($this->positive_at === null || $this->positivity_source === PositivitySource::BlindSubculture) {
            return null;
        }

        return (int) $this->loaded_at->diffInMinutes($this->positive_at);
    }

    /**
     * @return BelongsTo<SampleStudy, $this>
     */
    public function sampleStudy(): BelongsTo
    {
        return $this->belongsTo(SampleStudy::class);
    }

    /**
     * @return HasMany<BlindSubculture, $this>
     */
    public function blindSubcultures(): HasMany
    {
        return $this->hasMany(BlindSubculture::class)->orderBy('number');
    }

    /**
     * @return HasOne<GramStain, $this>
     */
    public function gramStain(): HasOne
    {
        return $this->hasOne(GramStain::class);
    }
}
