<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Cultures\Services\ReadingSchedule;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la lectura de un cultivo con una opción del protocolo congelado.
 * Qué pasa después lo decide el resultado de la opción (no hay reglas clínicas en el código):
 * sin desarrollo / flora mixta / no significativo → cierra; desarrollo → aislamientos;
 * reincubar → nueva lectura. La significancia de cada aislamiento la marca el bacteriólogo.
 */
class RecordReading
{
    public function __construct(
        private readonly ReadingSchedule $schedule,
        private readonly CultureClosure $closure,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  list<IsolateData>  $isolates
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, Culture $culture, string $optionCode, ?string $notes, array $isolates = []): Reading
    {
        return DB::transaction(function () use ($user, $culture, $optionCode, $notes, $isolates) {
            /** @var Culture $culture */
            $culture = Culture::query()->lockForUpdate()->with(['sampleStudy.protocolVersion', 'readings', 'plates'])->findOrFail($culture->id);
            $version = $culture->sampleStudy->protocolVersion;

            if ($culture->status !== CultureStatus::Incubating) {
                throw ValidationException::withMessages(['option_code' => 'Este cultivo ya no está en incubación.']);
            }

            $availableAt = $this->schedule->nextReadingAvailableAt($culture);
            if ($availableAt->isFuture()) {
                throw ValidationException::withMessages([
                    'option_code' => 'La lectura todavía no está disponible (incubación mínima del protocolo).',
                ]);
            }

            $option = collect($version->reading_options ?? [])->firstWhere('code', $optionCode);
            if ($option === null) {
                throw ValidationException::withMessages(['option_code' => 'Opción de lectura no válida para este protocolo.']);
            }
            $outcome = ReadingOutcome::from($option['outcome']);

            $this->authorize($user, $outcome);
            $this->validateOutcome($culture, $outcome, $isolates);

            $reading = $culture->readings()->create([
                'attempt' => $culture->readings->count() + 1,
                'option_code' => $optionCode,
                'outcome' => $outcome,
                'notes' => $notes,
                'read_by' => $user->id,
                'read_at' => Date::now(),
            ]);

            $created = collect();
            if ($outcome === ReadingOutcome::Growth) {
                foreach ($isolates as $index => $data) {
                    $created->push($culture->isolates()->create([
                        'number' => $index + 1,
                        'morphotype' => $data->morphotype,
                        'count_category' => $data->countCategory,
                        'plate_id' => $culture->plates->contains('id', $data->plateId) ? $data->plateId : null,
                        'is_significant' => $data->isSignificant,
                        'status' => $data->isSignificant ? IsolateStatus::InIdentification : IsolateStatus::NotSignificant,
                    ]));
                }
                $culture->update(['status' => CultureStatus::Workup, 'lock_version' => $culture->lock_version + 1]);
            }

            $this->auditor->record(CultureAuditAction::Read, $culture, after: [
                'attempt' => $reading->attempt,
                'option' => $optionCode,
                'outcome' => $outcome->value,
                'isolates' => $created->map(fn (Isolate $i) => [
                    'number' => $i->number,
                    'count' => $i->count_category,
                    'significant' => $i->is_significant,
                ])->all(),
            ], reason: $notes);

            $nextReadAt = $outcome === ReadingOutcome::Reincubate ? Date::now()->addHours((int) $version->reincubation_hours) : null;

            event(new CultureRead(
                culture: $culture,
                reading: $reading,
                significantIsolates: $created->filter(fn (Isolate $i) => $i->is_significant)->values(),
                nextReadAt: $nextReadAt,
                nextReadDueAt: $nextReadAt?->copy()->addHours($version->first_read_due_hours - $version->first_read_min_hours),
                userId: $user->id,
            ));

            $closing = match ($outcome) {
                ReadingOutcome::NoGrowth => CultureStatus::ClosedNegative,
                ReadingOutcome::MixedFlora => CultureStatus::ClosedContaminated,
                ReadingOutcome::NotSignificant => CultureStatus::ClosedNotSignificant,
                default => null,
            };
            if ($closing !== null) {
                $this->closure->close($culture, $closing);
            }

            return $reading;
        });
    }

    private function authorize(User $user, ReadingOutcome $outcome): void
    {
        // Leer "sin desarrollo" y leer con desarrollo son permisos distintos (ROLES_PERMISSIONS.md).
        $permission = $outcome === ReadingOutcome::NoGrowth ? Permission::CulturesReadNegative : Permission::CulturesReadPositive;

        if (! $user->can($permission->value)) {
            throw new AuthorizationException('No tenés permiso para registrar esta lectura.');
        }
    }

    /**
     * @param  list<IsolateData>  $isolates
     *
     * @throws ValidationException
     */
    private function validateOutcome(Culture $culture, ReadingOutcome $outcome, array $isolates): void
    {
        $version = $culture->sampleStudy->protocolVersion;

        if ($outcome === ReadingOutcome::Growth) {
            if ($isolates === []) {
                throw ValidationException::withMessages(['isolates' => 'Cargá al menos un aislamiento.']);
            }
            if (! collect($isolates)->contains(fn (IsolateData $i) => $i->isSignificant)) {
                throw ValidationException::withMessages([
                    'isolates' => 'Ningún aislamiento está marcado como significativo: usá la opción de desarrollo no significativo.',
                ]);
            }
            $categories = collect($version->count_categories ?? [])->pluck('code');
            foreach ($isolates as $index => $isolate) {
                if ($isolate->countCategory !== null && ! $categories->contains($isolate->countCategory)) {
                    throw ValidationException::withMessages(["isolates.{$index}.count_category" => 'Categoría de recuento no válida.']);
                }
            }
        }

        if ($outcome === ReadingOutcome::Reincubate) {
            $reincubations = $culture->readings->filter(fn (Reading $r) => $r->outcome === ReadingOutcome::Reincubate)->count();
            if ($version->reincubation_hours === null || $reincubations >= $version->max_reincubations) {
                throw ValidationException::withMessages([
                    'option_code' => 'El protocolo no permite más reincubaciones para este cultivo.',
                ]);
            }
        }
    }
}
