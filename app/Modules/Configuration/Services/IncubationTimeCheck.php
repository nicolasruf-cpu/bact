<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * Los tiempos de incubación del protocolo advierten pero no bloquean (D-0020): hay cargas
 * tardías y horas iniciales mal registradas. Antes de tiempo, la acción pide confirmación
 * y, confirmada, queda en la auditoría con cuánto faltaba y el motivo.
 * Se llama dentro de la transacción de la acción.
 */
class IncubationTimeCheck
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  string  $action  qué se registró, para la auditoría (p. ej. "Lectura del cultivo")
     *
     * @throws ValidationException
     */
    public function ensure(CarbonInterface $availableAt, EarlyConfirmation $early, Model $subject, string $action): void
    {
        if (! $availableAt->isFuture()) {
            return;
        }

        if (! $early->confirmed) {
            throw ValidationException::withMessages([
                'early_confirmed' => 'Todavía no se cumplió el tiempo de incubación del protocolo. Confirmá para registrar igual.',
            ]);
        }

        $this->auditor->record(ConfigurationAuditAction::IncubationTimeNotMet, $subject, after: [
            'action' => $action,
            'available_at' => $availableAt->toIso8601String(),
            'minutes_early' => (int) ceil(Date::now()->diffInMinutes($availableAt, absolute: true)),
        ], reason: $early->reason);
    }
}
