<?php

namespace App\Modules\CriticalResults\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\CriticalResults\Data\CommunicationData;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Enums\CriticalAuditAction;
use App\Modules\CriticalResults\Events\CriticalCommunicationRecorded;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\CriticalResults\Models\CriticalCommunication;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra un intento de comunicación (a quién, por qué medio, si se confirmó por relectura).
 * Un intento exitoso cierra el resultado crítico; uno fallido lo deja abierto.
 * Idempotente por idempotency_key.
 */
class RecordCriticalCommunication
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, CriticalAlert $alert, CommunicationData $data): CriticalCommunication
    {
        if (! $user->can(Permission::CriticalCommunicate->value)) {
            throw new AuthorizationException('No tenés permiso para comunicar resultados críticos.');
        }

        return DB::transaction(function () use ($user, $alert, $data) {
            $existing = CriticalCommunication::query()->where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            /** @var CriticalAlert $alert */
            $alert = CriticalAlert::query()->lockForUpdate()->findOrFail($alert->id);
            if ($alert->status !== CriticalAlertStatus::Open) {
                throw ValidationException::withMessages(['recipient_name' => 'Este resultado crítico ya fue comunicado.']);
            }

            $communication = CriticalCommunication::create([
                'idempotency_key' => $data->idempotencyKey,
                'critical_alert_id' => $alert->id,
                'recipient_name' => $data->recipientName,
                'recipient_role' => $data->recipientRole,
                'channel' => $data->channel,
                'successful' => $data->successful,
                'read_back_confirmed' => $data->readBackConfirmed,
                'notes' => $data->notes,
                'communicated_by' => $user->id,
                'communicated_at' => Date::now(),
            ]);

            if ($data->successful) {
                $alert->update([
                    'status' => CriticalAlertStatus::Communicated,
                    'closed_at' => Date::now(),
                    'lock_version' => $alert->lock_version + 1,
                ]);
            }

            $this->auditor->record(CriticalAuditAction::CommunicationAttempt, $alert, after: [
                'recipient' => $data->recipientName,
                'role' => $data->recipientRole,
                'channel' => $data->channel->value,
                'successful' => $data->successful,
                'read_back_confirmed' => $data->readBackConfirmed,
            ], reason: $data->notes);

            event(new CriticalCommunicationRecorded($alert, $communication, $user->id));

            return $communication;
        });
    }
}
