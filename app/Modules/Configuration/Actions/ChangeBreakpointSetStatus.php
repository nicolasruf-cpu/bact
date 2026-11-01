<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de un set de puntos de corte: draft → approved → active → retired.
 * Aprueba y pone vigente solo el Director técnico responsable (Q-A8). Poner vigente un
 * set retira el set real que estaba vigente; los antibiogramas ya interpretados
 * conservan el set que usaron. El set ficticio de demo no se toca.
 */
class ChangeBreakpointSetStatus
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, BreakpointSet $set, ProtocolVersionStatus $target, int $lockVersion, ?string $reason = null): BreakpointSet
    {
        return DB::transaction(function () use ($user, $set, $target, $lockVersion, $reason) {
            /** @var BreakpointSet $set */
            $set = BreakpointSet::query()->lockForUpdate()->findOrFail($set->id);

            if ($set->is_synthetic) {
                throw ValidationException::withMessages(['status' => 'El set de demostración no se modifica.']);
            }
            if ($set->status === $target) {
                return $set;
            }

            $this->authorize($user, $set, $target);

            if ($set->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'El set cambió. Recargá la página.']);
            }

            $allowed = match ($target) {
                ProtocolVersionStatus::Approved => $set->status === ProtocolVersionStatus::Draft,
                ProtocolVersionStatus::Active => $set->status === ProtocolVersionStatus::Approved,
                ProtocolVersionStatus::Retired => true,
                ProtocolVersionStatus::Draft => false,
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de {$set->status->label()} a {$target->label()}."]);
            }
            if ($target === ProtocolVersionStatus::Approved && ! $set->breakpoints()->exists()) {
                throw ValidationException::withMessages(['status' => 'El set no tiene puntos de corte cargados.']);
            }
            if ($target === ProtocolVersionStatus::Retired && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'Indicá el motivo.']);
            }

            if ($target === ProtocolVersionStatus::Active) {
                $this->retireCurrent($set);
            }

            $previous = $set->status;
            $set->update([
                'status' => $target,
                'lock_version' => $set->lock_version + 1,
                ...match ($target) {
                    ProtocolVersionStatus::Approved => ['approved_by' => $user->id, 'approved_at' => Date::now()],
                    ProtocolVersionStatus::Active => ['activated_at' => Date::now()],
                    default => ['retired_at' => Date::now()],
                },
            ]);

            $this->auditor->record(
                match ($target) {
                    ProtocolVersionStatus::Approved => ConfigurationAuditAction::BreakpointSetApproved,
                    ProtocolVersionStatus::Active => ConfigurationAuditAction::BreakpointSetActivated,
                    default => ConfigurationAuditAction::BreakpointSetRetired,
                },
                $set,
                before: ['status' => $previous->value],
                after: ['status' => $target->value],
                reason: $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            );

            return $set;
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $user, BreakpointSet $set, ProtocolVersionStatus $target): void
    {
        $discardingDraft = $target === ProtocolVersionStatus::Retired && $set->status === ProtocolVersionStatus::Draft;
        $allowed = $discardingDraft
            ? $user->can(Permission::ConfigClinicalDraft->value) || $user->can(Permission::CLINICAL_APPROVE)
            : $user->can(Permission::CLINICAL_APPROVE);

        if (! $allowed) {
            throw new AuthorizationException('Solo el Director técnico responsable aprueba, pone vigente o retira puntos de corte.');
        }
    }

    private function retireCurrent(BreakpointSet $set): void
    {
        $current = BreakpointSet::query()
            ->where('status', ProtocolVersionStatus::Active->value)
            ->where('is_synthetic', false)
            ->whereKeyNot($set->id)
            ->lockForUpdate()
            ->get();

        foreach ($current as $old) {
            $old->update(['status' => ProtocolVersionStatus::Retired, 'retired_at' => Date::now(), 'lock_version' => $old->lock_version + 1]);
            $this->auditor->record(ConfigurationAuditAction::BreakpointSetRetired, $old,
                before: ['status' => ProtocolVersionStatus::Active->value],
                after: ['status' => ProtocolVersionStatus::Retired->value, 'replaced_by_set_id' => $set->id],
            );
        }
    }
}
