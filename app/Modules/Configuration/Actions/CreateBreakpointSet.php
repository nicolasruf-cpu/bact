<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Set de puntos de corte nuevo en borrador (una edición del M100 del laboratorio, Q-C4).
 * Puede partir vacío o de una copia de otro set real; nunca del set ficticio de demo.
 */
class CreateBreakpointSet
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, string $name, string $edition, ?string $notes, ?BreakpointSet $copyFrom = null): BreakpointSet
    {
        if (! $user->can(Permission::ConfigClinicalDraft->value)) {
            throw new AuthorizationException('No tenés permiso para preparar configuración clínica.');
        }
        if ($copyFrom?->is_synthetic) {
            throw ValidationException::withMessages(['copy_from_id' => 'El set de demostración tiene valores ficticios: no se copia.']);
        }

        return DB::transaction(function () use ($user, $name, $edition, $notes, $copyFrom) {
            $set = BreakpointSet::create([
                'name' => trim($name),
                'edition' => trim($edition),
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'status' => ProtocolVersionStatus::Draft,
                'is_synthetic' => false,
                'created_by' => $user->id,
            ]);

            $copied = 0;
            if ($copyFrom !== null) {
                foreach ($copyFrom->breakpoints as $bp) {
                    $set->breakpoints()->create($bp->only(['organism_group', 'antibiotic_id', 'method', 'susceptible_value', 'resistant_value']));
                    $copied++;
                }
            }

            $this->auditor->record(ConfigurationAuditAction::BreakpointSetDrafted, $set, after: [
                'name' => $set->name,
                'edition' => $set->edition,
                'copied_from_set_id' => $copyFrom?->id,
                'breakpoints' => $copied,
            ]);

            return $set;
        });
    }
}
