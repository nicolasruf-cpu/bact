<?php

namespace App\Modules\Susceptibility\Models;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $isolate_id
 * @property int $ast_panel_id
 * @property AstMethod $method
 * @property int|null $breakpoint_set_id
 * @property SusceptibilityTestStatus $status
 * @property int $set_up_by
 * @property Carbon $set_up_at
 * @property int|null $read_by
 * @property Carbon|null $read_at
 * @property int $lock_version
 * @property-read Isolate $isolate
 * @property-read AstPanel $panel
 * @property-read BreakpointSet|null $breakpointSet
 * @property-read Collection<int, SusceptibilityResult> $results
 */
class SusceptibilityTest extends Model
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
            'method' => AstMethod::class,
            'status' => SusceptibilityTestStatus::class,
            'set_up_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Isolate, $this>
     */
    public function isolate(): BelongsTo
    {
        return $this->belongsTo(Isolate::class);
    }

    /**
     * @return BelongsTo<AstPanel, $this>
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(AstPanel::class, 'ast_panel_id');
    }

    /**
     * @return BelongsTo<BreakpointSet, $this>
     */
    public function breakpointSet(): BelongsTo
    {
        return $this->belongsTo(BreakpointSet::class);
    }

    /**
     * @return HasMany<SusceptibilityResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(SusceptibilityResult::class);
    }
}
