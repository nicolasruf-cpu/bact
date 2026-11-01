<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Services\ProtocolVersionRules;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aprueba un borrador (draft → approved). Solo el Director técnico responsable (Q-A8).
 * Aprobar no lo pone en uso: eso es activarlo.
 */
class ApproveProtocolVersion
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly ProtocolVersionRules $rules,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ProtocolVersion $version, int $lockVersion, ?string $note = null): ProtocolVersion
    {
        if (! $user->can(Permission::CLINICAL_APPROVE)) {
            throw new AuthorizationException('Solo el Director técnico responsable aprueba la configuración clínica.');
        }

        return DB::transaction(function () use ($user, $version, $lockVersion, $note) {
            /** @var ProtocolVersion $version */
            $version = ProtocolVersion::query()->lockForUpdate()->with('media')->findOrFail($version->id);

            if ($version->status === ProtocolVersionStatus::Approved) {
                return $version;
            }
            if ($version->status !== ProtocolVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'Solo se aprueba un borrador.']);
            }
            if ($version->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'El borrador cambió mientras lo revisabas. Recargá la página.']);
            }

            $missing = $this->rules->missingForApproval($version);
            if ($missing !== []) {
                throw ValidationException::withMessages(['version' => $missing]);
            }

            $version->update([
                'status' => ProtocolVersionStatus::Approved,
                'approved_by' => $user->id,
                'approved_at' => Date::now(),
                'lock_version' => $version->lock_version + 1,
            ]);

            $this->auditor->record(ConfigurationAuditAction::ProtocolVersionApproved, $version,
                before: ['status' => ProtocolVersionStatus::Draft->value],
                after: ['status' => ProtocolVersionStatus::Approved->value, 'is_reference_template' => $version->is_reference_template],
                reason: $note !== null && trim($note) !== '' ? trim($note) : null,
            );

            return $version;
        });
    }
}
