<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\IsolateReferred;
use App\Modules\Identification\Models\Referral;
use App\Modules\Identity\Enums\Permission;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deriva un aislamiento a otro laboratorio (revisión de marchas B9), con el motivo
 * (serogrupo, confirmación de mecanismo…). El resultado queda pendiente en la mesada y,
 * cuando llega, sale en el informe.
 */
class ReferIsolate
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Isolate $isolate, string $destination, string $reason, ?CarbonInterface $sentAt = null): Referral
    {
        if (! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para derivar aislamientos.');
        }
        if (blank($destination) || blank($reason)) {
            throw ValidationException::withMessages(['destination' => 'Indicá a dónde se deriva y para qué.']);
        }

        return DB::transaction(function () use ($user, $isolate, $destination, $reason, $sentAt) {
            /** @var Isolate $isolate */
            $isolate = Isolate::query()->lockForUpdate()->findOrFail($isolate->id);
            $at = $sentAt ?? Date::now();
            if ($at->isFuture()) {
                throw ValidationException::withMessages(['sent_at' => 'La hora de envío no puede ser futura.']);
            }

            $referral = Referral::create([
                'isolate_id' => $isolate->id,
                'destination' => trim($destination),
                'reason' => trim($reason),
                'sent_by' => $user->id,
                'sent_at' => $at,
            ]);

            $this->auditor->record(IdentificationAuditAction::Referred, $isolate, after: [
                'destination' => $referral->destination,
                'sent_at' => $at->toIso8601String(),
            ], reason: $reason);

            event(new IsolateReferred($referral, $user->id));

            return $referral;
        });
    }
}
