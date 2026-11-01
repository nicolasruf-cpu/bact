<?php

namespace App\Modules\CriticalResults\Models;

use App\Models\User;
use App\Modules\CriticalResults\Enums\CommunicationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intento de comunicación de un resultado crítico (exitoso o no). Inmutable.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property int $critical_alert_id
 * @property string $recipient_name
 * @property string|null $recipient_role
 * @property CommunicationChannel $channel
 * @property bool $successful
 * @property bool $read_back_confirmed
 * @property string|null $notes
 * @property int $communicated_by
 * @property Carbon $communicated_at
 * @property-read User $communicator
 */
class CriticalCommunication extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'successful' => 'boolean',
            'read_back_confirmed' => 'boolean',
            'communicated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function communicator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'communicated_by');
    }
}
