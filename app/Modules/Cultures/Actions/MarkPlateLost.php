<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Da por perdida una placa (rota, contaminada, desecada…) con el motivo (revisión de
 * marchas B7). Si se resiembra, la placa nueva repite el medio y la incubación desde ahora;
 * la lectura siguiente espera su incubación mínima. Una placa perdida no se lee más.
 */
class MarkPlateLost
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, Plate $plate, string $reason, bool $resow): ?Plate
    {
        if (! $user->can(Permission::CulturesInoculate->value) && ! $user->can(Permission::CulturesReadPositive->value)) {
            throw new AuthorizationException('No tenés permiso para dar por perdida una placa.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Indicá por qué se perdió la placa.']);
        }

        return DB::transaction(function () use ($user, $plate, $reason, $resow) {
            /** @var Plate $plate */
            $plate = Plate::query()->lockForUpdate()->with(['culture', 'medium'])->findOrFail($plate->id);
            if ($plate->status !== PlateStatus::Incubating) {
                throw ValidationException::withMessages(['reason' => 'La placa ya no está en incubación.']);
            }
            if ($plate->culture->status->isClosed()) {
                throw ValidationException::withMessages(['reason' => 'El cultivo ya está cerrado.']);
            }

            $plate->update([
                'status' => PlateStatus::Lost,
                'lost_reason' => trim($reason),
                'lost_at' => Date::now(),
                'lost_by' => $user->id,
            ]);

            $replacement = null;
            if ($resow) {
                $replacement = $plate->culture->plates()->create([
                    'medium_id' => $plate->medium_id,
                    'replaces_plate_id' => $plate->id,
                    'inoculum' => $plate->inoculum,
                    'incubation_temperature' => $plate->incubation_temperature,
                    'atmosphere' => $plate->atmosphere,
                    'read_from_hours' => $plate->read_from_hours,
                    'read_until_hours' => $plate->read_until_hours,
                    'is_enrichment' => $plate->is_enrichment,
                    'inoculated_at' => Date::now(),
                    'status' => PlateStatus::Incubating,
                ]);
            }

            $this->auditor->record(CultureAuditAction::PlateLost, $plate->culture, after: [
                'plate' => $plate->id,
                'medium' => $plate->medium->code,
                'resown_plate' => $replacement?->id,
            ], reason: $reason);

            return $replacement;
        });
    }
}
