<?php

namespace App\Modules\Identification\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use App\Modules\Identification\Events\ReferralResultRecorded;
use App\Modules\Identification\Models\Referral;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra lo que informó el laboratorio de referencia. Sale en el informe del aislamiento.
 */
class RecordReferralResult
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Referral $referral, string $result): Referral
    {
        if (! $user->can(Permission::IdentificationPerform->value)) {
            throw new AuthorizationException('No tenés permiso para registrar el resultado.');
        }
        if (blank($result)) {
            throw ValidationException::withMessages(['result' => 'Ingresá el resultado informado.']);
        }

        return DB::transaction(function () use ($user, $referral, $result) {
            /** @var Referral $referral */
            $referral = Referral::query()->lockForUpdate()->with('isolate')->findOrFail($referral->id);
            if ($referral->result_at !== null) {
                throw ValidationException::withMessages(['result' => 'La derivación ya tiene resultado.']);
            }

            $referral->update([
                'result' => trim($result),
                'result_recorded_by' => $user->id,
                'result_at' => Date::now(),
            ]);

            $this->auditor->record(IdentificationAuditAction::ReferralResultRecorded, $referral->isolate, after: [
                'destination' => $referral->destination,
                'result' => $referral->result,
            ]);

            event(new ReferralResultRecorded($referral, $user->id));

            return $referral;
        });
    }
}
