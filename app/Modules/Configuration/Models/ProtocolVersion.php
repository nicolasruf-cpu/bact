<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Data\BloodCultureSettings;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versión de un protocolo. Una muestra congela la versión vigente al recibirse.
 *
 * @property int $id
 * @property int $protocol_id
 * @property int $version
 * @property ProtocolVersionStatus $status
 * @property bool $is_reference_template
 * @property ProcessingKind $processing_kind
 * @property string|null $source
 * @property string|null $notes
 * @property int $first_read_min_hours
 * @property int $first_read_due_hours
 * @property int|null $reincubation_hours
 * @property int $max_reincubations
 * @property bool $allow_bulk_negative_confirmation
 * @property list<array{code: string, label: string, outcome: string}>|null $reading_options
 * @property list<array{code: string, label: string}>|null $count_categories
 * @property array<string, mixed>|null $blood_culture_settings
 * @property int|null $ast_read_min_hours
 * @property int|null $ast_read_due_hours
 * @property Carbon|null $approved_at
 * @property Carbon|null $activated_at
 * @property-read Protocol $protocol
 * @property-read Collection<int, ProtocolVersionMedium> $media
 */
class ProtocolVersion extends Model
{
    protected $guarded = ['id', 'active_protocol_id'];

    protected function casts(): array
    {
        return [
            'status' => ProtocolVersionStatus::class,
            'is_reference_template' => 'boolean',
            'processing_kind' => ProcessingKind::class,
            'allow_bulk_negative_confirmation' => 'boolean',
            'reading_options' => 'array',
            'count_categories' => 'array',
            'blood_culture_settings' => 'array',
            'approved_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function isBloodCulture(): bool
    {
        return $this->processing_kind === ProcessingKind::BloodBottle;
    }

    public function bloodCultureSettings(): ?BloodCultureSettings
    {
        return $this->blood_culture_settings === null ? null : BloodCultureSettings::fromArray($this->blood_culture_settings);
    }

    /**
     * @return BelongsTo<Protocol, $this>
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    /**
     * @return HasMany<ProtocolVersionMedium, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProtocolVersionMedium::class)->orderBy('position');
    }
}
