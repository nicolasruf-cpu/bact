<?php

namespace App\Modules\Organization\Models;

use App\Modules\Organization\Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sede del laboratorio (ADR-0008). También es el "team" de spatie/laravel-permission:
 * los roles de cada usuario se asignan por sede.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'is_active'])]
#[UseFactory(SiteFactory::class)]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @param  Builder<Site>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
