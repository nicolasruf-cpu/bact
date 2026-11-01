<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retira una versión con motivo. Un borrador lo puede descartar quien prepara la
 * configuración; una versión aprobada o vigente, solo el responsable (Q-A8). Retirar la
 * vigente sin reemplazo deja el estudio sin protocolo: la recepción lo rechaza.
 * Nada se borra.
 */
class RetireProtocolVersion
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ProtocolVersion $version, string $reason, int $lockVersion): ProtocolVersion
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indicá el motivo.']);
        }

        return DB::transaction(function () use ($user, $version, $reason, $lockVersion) {
            /** @var ProtocolVersion $version */
            $version = ProtocolVersion::query()->lockForUpdate()->findOrFail($version->id);

            if ($version->status === ProtocolVersionStatus::Retired) {
                return $version;
            }

            $allowed = $version->status === ProtocolVersionStatus::Draft
                ? $user->can(Permission::ConfigClinicalDraft->value) || $user->can(Permission::CLINICAL_APPROVE)
                : $user->can(Permission::CLINICAL_APPROVE);
            if (! $allowed) {
                throw new AuthorizationException('No tenés permiso para retirar esta versión.');
            }
            if ($version->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'La versión cambió. Recargá la página.']);
            }

            $previous = $version->status;
            $version->update([
                'status' => ProtocolVersionStatus::Retired,
                'retired_at' => Date::now(),
                'lock_version' => $version->lock_version + 1,
            ]);

            $this->auditor->record(ConfigurationAuditAction::ProtocolVersionRetired, $version,
                before: ['status' => $previous->value],
                after: ['status' => ProtocolVersionStatus::Retired->value],
                reason: trim($reason),
            );

            return $version;
        });
    }
}
