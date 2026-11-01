<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Services\BottleOutcome;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Descarga de un frasco del equipo como negativo al cumplirse los días del protocolo.
 * (El frasco manual se cierra solo al leer sin desarrollo el último repique.)
 */
class ConfirmNegativeBottle
{
    public function __construct(private readonly BottleOutcome $outcome) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle): BloodCultureBottle
    {
        if (! $user->can(Permission::BloodCulturesConfirmNegative->value)) {
            throw new AuthorizationException('No tenés permiso para informar frascos negativos.');
        }

        return DB::transaction(function () use ($user, $bottle) {
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
            if ($bottle->incubation_ends_at->isFuture()) {
                throw ValidationException::withMessages([
                    'bottle' => "Todavía no se cumplieron los {$bottle->incubationDays()} días de incubación del protocolo.",
                ]);
            }

            $this->outcome->closeNegative($bottle, $user->id);

            return $bottle;
        });
    }
}
