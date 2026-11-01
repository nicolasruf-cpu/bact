<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use App\Modules\Organization\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Evento de auditoría. Inmutable: solo se crea a través de `Auditor`.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property string $action
 * @property int|null $user_id
 * @property int|null $site_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $reason
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property string|null $previous_hash
 * @property string $hash
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de auditoría no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los eventos de auditoría no se eliminan.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
