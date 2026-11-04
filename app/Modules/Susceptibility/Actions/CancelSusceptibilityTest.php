<?php

namespace App\Modules\Susceptibility\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Events\SusceptibilityTestCancelled;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Descarta un antibiograma sin leer, con motivo (p. ej., se preparó con una identificación
 * presuntiva que no se confirmó, o el inóculo no fue el correcto). Queda en el historial y se
 * puede preparar otro.
 */
class CancelSusceptibilityTest
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, SusceptibilityTest $test, string $reason): SusceptibilityTest
    {
        if (! $user->can(Permission::AstSetup->value)) {
            throw new AuthorizationException('No tenés permiso para descartar antibiogramas.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Indicá por qué se descarta.']);
        }

        return DB::transaction(function () use ($user, $test, $reason) {
            /** @var SusceptibilityTest $test */
            $test = SusceptibilityTest::query()->lockForUpdate()->with(['results', 'isolate'])->findOrFail($test->id);
            if ($test->status !== SusceptibilityTestStatus::SetUp || $test->results->contains(fn ($r) => $r->isRead())) {
                throw ValidationException::withMessages(['cancellation_reason' => 'Solo se descarta un antibiograma sin leer.']);
            }

            $test->update([
                'status' => SusceptibilityTestStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => Date::now(),
                'cancellation_reason' => trim($reason),
                'lock_version' => $test->lock_version + 1,
            ]);

            $this->auditor->record(SusceptibilityAuditAction::Cancelled, $test, after: ['status' => SusceptibilityTestStatus::Cancelled->value], reason: $reason);

            event(new SusceptibilityTestCancelled($test, $test->isolate->status === IsolateStatus::Identified, $user->id));

            return $test;
        });
    }
}
