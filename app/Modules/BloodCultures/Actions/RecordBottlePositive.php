<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Enums\PositivitySource;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Services\BottleOutcome;
use App\Modules\Identity\Enums\Permission;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la positividad del frasco: la alarma del equipo (con la hora que informa el equipo)
 * o signos visibles en un frasco manual. El positivo por repique lo registra la lectura del repique.
 */
class RecordBottlePositive
{
    public function __construct(private readonly BottleOutcome $outcome) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, BloodCultureBottle $bottle, ?CarbonInterface $positiveAt = null, ?string $notes = null): BloodCultureBottle
    {
        if (! $user->can(Permission::BloodCulturesSignalPositive->value)) {
            throw new AuthorizationException('No tenés permiso para registrar frascos positivos.');
        }

        return DB::transaction(function () use ($user, $bottle, $positiveAt, $notes) {
            /** @var BloodCultureBottle $bottle */
            $bottle = BloodCultureBottle::query()->lockForUpdate()->with('sampleStudy.protocolVersion')->findOrFail($bottle->id);

            if ($bottle->status !== BottleStatus::Incubating) {
                throw ValidationException::withMessages(['positive_at' => 'Este frasco ya no está en incubación.']);
            }

            $positiveAt ??= Date::now();
            if ($positiveAt->isFuture()) {
                throw ValidationException::withMessages(['positive_at' => 'La hora de positividad no puede ser futura.']);
            }
            if ($positiveAt->lessThan($bottle->loaded_at)) {
                throw ValidationException::withMessages(['positive_at' => 'La hora de positividad no puede ser anterior a la carga.']);
            }

            $source = $bottle->isAutomated() ? PositivitySource::Instrument : PositivitySource::Visual;
            $this->outcome->markPositive($bottle, $source, $positiveAt, $user->id, needsSubculture: true, notes: $notes);

            return $bottle;
        });
    }
}
