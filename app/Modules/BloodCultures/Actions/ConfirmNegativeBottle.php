<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Services\BottleOutcome;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Services\IncubationTimeCheck;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Descarga de un frasco del equipo como negativo al cumplirse los días del protocolo
 * (antes, con confirmación: D-0020).
 * (El frasco manual se cierra solo al leer sin desarrollo el último repique.)
 */
class ConfirmNegativeBottle
{
    public function __construct(
        private readonly BottleOutcome $outcome,
        private readonly IncubationTimeCheck $timeCheck,
    ) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle, EarlyConfirmation $early = new EarlyConfirmation): BloodCultureBottle
    {
        if (! $user->can(Permission::BloodCulturesConfirmNegative->value)) {
            throw new AuthorizationException('No tenés permiso para informar frascos negativos.');
        }

        return DB::transaction(function () use ($user, $bottle, $early) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()->with('sampleStudy.protocolVersion')->findOrFail($bottle->id);

            if ($bottle->status === BottleStatus::Negative) {
                return $bottle;
            }
            if ($bottle->status !== BottleStatus::Incubating) {
                throw ValidationException::withMessages(['bottle' => 'Este frasco ya no está en incubación.']);
            }
            if (! $bottle->isAutomated()) {
                throw ValidationException::withMessages(['bottle' => 'El frasco manual se cierra al leer sin desarrollo el último repique.']);
            }
            $this->timeCheck->ensure($bottle->incubation_ends_at, $early, $bottle, "Frasco negativo antes de los {$bottle->incubationDays()} días");

            $this->outcome->closeNegative($bottle, $user->id);

            return $bottle;
        });
    }
}
