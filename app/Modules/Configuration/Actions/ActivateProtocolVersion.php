<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pone vigente una versión aprobada (approved → active) y retira la que estaba vigente.
 * Las muestras ya recibidas siguen con la versión que congelaron (MICROBIOLOGY_RULES §1);
 * las que se reciban desde ahora usan la nueva.
 */
class ActivateProtocolVersion
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ProtocolVersion $version, int $lockVersion): ProtocolVersion
    {
        if (! $user->can(Permission::CLINICAL_APPROVE)) {
            throw new AuthorizationException('Solo el Director técnico responsable pone vigente la configuración clínica.');
        }

        return DB::transaction(function () use ($version, $lockVersion) {
            Protocol::query()->lockForUpdate()->findOrFail($version->protocol_id);
            /** @var ProtocolVersion $version */
            $version = ProtocolVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($version->status === ProtocolVersionStatus::Active) {
                return $version;
            }
            if ($version->status !== ProtocolVersionStatus::Approved) {
                throw ValidationException::withMessages(['version' => 'Solo se pone vigente una versión aprobada.']);
            }
            if ($version->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'La versión cambió. Recargá la página.']);
            }

            /** @var ProtocolVersion|null $current */
            $current = ProtocolVersion::query()
                ->where('protocol_id', $version->protocol_id)
                ->where('status', ProtocolVersionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->update([
                    'status' => ProtocolVersionStatus::Retired,
                    'retired_at' => Date::now(),
                    'lock_version' => $current->lock_version + 1,
                ]);
                $this->auditor->record(ConfigurationAuditAction::ProtocolVersionRetired, $current,
                    before: ['status' => ProtocolVersionStatus::Active->value],
                    after: ['status' => ProtocolVersionStatus::Retired->value, 'replaced_by_version' => $version->version],
                );
            }

            $version->update([
                'status' => ProtocolVersionStatus::Active,
                'activated_at' => Date::now(),
                'lock_version' => $version->lock_version + 1,
            ]);

            $this->auditor->record(ConfigurationAuditAction::ProtocolVersionActivated, $version,
                before: ['status' => ProtocolVersionStatus::Approved->value],
                after: ['status' => ProtocolVersionStatus::Active->value, 'replaces_version' => $current?->version],
            );

            return $version;
        });
    }
}
