<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Configuration\Services\IncubationTimeCheck;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Data\PlateObservationData;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Enums\PlateObservation;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Events\CultureRead;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Cultures\Services\ReadingSchedule;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la lectura de un cultivo con una opción del protocolo congelado.
 * Qué pasa después lo decide el resultado de la opción (no hay reglas clínicas en el código):
 * sin desarrollo / flora mixta / no significativo → cierra; desarrollo → aislamientos;
 * reincubar → nueva lectura. La significancia de cada aislamiento la marca el bacteriólogo;
 * un probable contaminante se identifica igual, sin antibiograma (A4).
 *
 * Con cronograma de varias lecturas (D-0017): antes de la última, "sin desarrollo" programa
 * la siguiente; con desarrollo se cargan los aislamientos y se siguen haciendo las lecturas
 * programadas, en las que solo cabe desarrollo nuevo o sin desarrollo nuevo. La última
 * lectura no se registra con un caldo de enriquecimiento sin repicar. Antes de la hora
 * de la lectura se puede registrar igual, con confirmación (D-0020).
 *
 * Cada lectura registra qué se vio en cada placa que toca leer (A3). Una placa se lee solo
 * en las lecturas del cronograma dentro de sus horas; después de la última queda finalizada.
 * La lectura guarda el texto con que queda en el historial (A6): «sin desarrollo nuevo»
 * después de un desarrollo y el de la lectura intermedia salen del protocolo.
 */
class RecordReading
{
    public function __construct(
        private readonly ReadingSchedule $schedule,
        private readonly CultureClosure $closure,
        private readonly IncubationTimeCheck $timeCheck,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @param  list<IsolateData>  $isolates
     * @param  list<PlateObservationData>  $plates
     *
     * @throws ValidationException|AuthorizationException
     */
    public function handle(
        User $user,
        Culture $culture,
        string $optionCode,
        ?string $notes,
        array $isolates = [],
        EarlyConfirmation $early = new EarlyConfirmation,
        array $plates = [],
        ?string $countCategory = null,
    ): Reading {
        return DB::transaction(function () use ($user, $culture, $optionCode, $notes, $isolates, $early, $plates, $countCategory) {
            /** @var Culture $culture */
            $culture = Culture::query()->lockForUpdate()->with(['sampleStudy.protocolVersion', 'readings', 'plates'])->findOrFail($culture->id);
            $version = $culture->sampleStudy->protocolVersion;

            $inWorkup = $culture->status === CultureStatus::Workup;
            if (! ($culture->status === CultureStatus::Incubating || ($inWorkup && ! $this->schedule->isComplete($culture)))) {
                throw ValidationException::withMessages(['option_code' => 'Este cultivo no tiene lecturas pendientes.']);
            }

            $option = collect($version->reading_options ?? [])->firstWhere('code', $optionCode);
            if ($option === null) {
                throw ValidationException::withMessages(['option_code' => 'Opción de lectura no válida para este protocolo.']);
            }
            $outcome = ReadingOutcome::from($option['outcome']);

            $this->authorize($user, $outcome);
            $this->validateOutcome($culture, $outcome, $isolates, $countCategory);

            $isLast = $this->schedule->isLastStep($culture);
            if ($inWorkup && ! in_array($outcome, [ReadingOutcome::Growth, ReadingOutcome::NoGrowth, ReadingOutcome::Reincubate], true)) {
                throw ValidationException::withMessages([
                    'option_code' => 'El cultivo ya tiene aislamientos: registrá desarrollo nuevo o sin desarrollo nuevo.',
                ]);
            }
            $closes = in_array($outcome, [ReadingOutcome::MixedFlora, ReadingOutcome::NotSignificant], true)
                || ($outcome !== ReadingOutcome::Reincubate && $isLast);
            if ($closes && $this->schedule->pendingEnrichments($culture)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'option_code' => 'Falta repicar el caldo de enriquecimiento antes de la última lectura.',
                ]);
            }
            // Antes de la última lectura programada, sin desarrollo no cierra: espera la siguiente.
            $continues = in_array($outcome, [ReadingOutcome::NoGrowth, ReadingOutcome::Growth], true) && ! $isLast;
            $nextStep = $continues ? $this->schedule->followingStep($culture) : null;

            $stepHours = $this->schedule->stepHours($culture);
            $observations = $this->observations($culture, $outcome, $inWorkup, $stepHours, $plates, $isolates);

            $this->timeCheck->ensure($this->schedule->nextReadingAvailableAt($culture), $early, $culture, 'Lectura del cultivo');

            $reading = $culture->readings()->create([
                'attempt' => $culture->readings->count() + 1,
                'option_code' => $optionCode,
                'outcome' => $outcome,
                'label' => $this->label($version->no_new_growth_label, $version->intermediate_no_growth_label, (string) $option['label'], $outcome, $inWorkup, $continues),
                'count_category' => in_array($outcome, [ReadingOutcome::NotSignificant, ReadingOutcome::MixedFlora], true) ? $countCategory : null,
                'notes' => $notes,
                'read_by' => $user->id,
                'read_at' => Date::now(),
            ]);
            foreach ($observations as $observation) {
                $reading->plateReadings()->create([
                    'plate_id' => $observation->plateId,
                    'observation' => $observation->observation,
                    'notes' => filled($observation->notes) ? $observation->notes : null,
                ]);
            }

            $created = collect();
            if ($outcome === ReadingOutcome::Growth) {
                $offset = (int) $culture->isolates()->max('number');
                foreach ($isolates as $index => $data) {
                    $significance = $data->significance();
                    $created->push($culture->isolates()->create([
                        'number' => $offset + $index + 1,
                        'morphotype' => $data->morphotype,
                        'count_category' => $data->countCategory,
                        'plate_id' => $culture->plates->contains('id', $data->plateId) ? $data->plateId : null,
                        'is_significant' => $significance->isSignificant(),
                        'significance' => $significance,
                        'status' => $significance === IsolateSignificance::NotSignificant ? IsolateStatus::NotSignificant : IsolateStatus::InIdentification,
                    ]));
                }
                if (! $inWorkup) {
                    $culture->update(['status' => CultureStatus::Workup, 'lock_version' => $culture->lock_version + 1]);
                }
            }

            // Placas que terminaron su parte del cronograma (p. ej., una que se lee solo hasta las 48 h).
            if ($outcome !== ReadingOutcome::Reincubate) {
                $culture->plates
                    ->filter(fn (Plate $p) => $p->status === PlateStatus::Incubating && $p->read_until_hours !== null && $stepHours >= $p->read_until_hours)
                    ->each(fn (Plate $p) => $p->update(['status' => PlateStatus::Finished]));
            }

            $this->auditor->record(CultureAuditAction::Read, $culture, after: [
                'attempt' => $reading->attempt,
                'option' => $optionCode,
                'outcome' => $outcome->value,
                'count' => $reading->count_category,
                'plates' => $observations->map(fn (PlateObservationData $o) => ['plate' => $o->plateId, 'observation' => $o->observation->value])->values()->all(),
                'isolates' => $created->map(fn (Isolate $i) => [
                    'number' => $i->number,
                    'count' => $i->count_category,
                    'significance' => $i->significance->value,
                ])->all(),
            ], reason: $notes);

            [$nextReadAt, $nextReadDueAt] = match (true) {
                $outcome === ReadingOutcome::Reincubate => [
                    $at = Date::now()->addHours((int) $version->reincubation_hours),
                    $at->copy()->addHours($version->first_read_due_hours - $version->first_read_min_hours),
                ],
                $nextStep !== null => $nextStep,
                default => [null, null],
            };

            event(new CultureRead(
                culture: $culture,
                reading: $reading,
                significantIsolates: $created->filter(fn (Isolate $i) => $i->status === IsolateStatus::InIdentification)->values(),
                nextReadAt: $nextReadAt,
                nextReadDueAt: $nextReadDueAt,
                userId: $user->id,
            ));

            if ($continues) {
                return $reading;
            }

            $closing = match ($outcome) {
                ReadingOutcome::NoGrowth => $inWorkup ? null : CultureStatus::ClosedNegative,
                ReadingOutcome::MixedFlora => CultureStatus::ClosedContaminated,
                ReadingOutcome::NotSignificant => CultureStatus::ClosedNotSignificant,
                default => null,
            };
            if ($closing !== null) {
                $this->closure->close($culture, $closing);
            } elseif ($inWorkup || $outcome === ReadingOutcome::Growth) {
                // Última lectura de un cultivo con aislamientos: cierra si ya están todos resueltos.
                $this->closure->closeIfResolved($culture->fresh() ?? $culture);
            }

            return $reading;
        });
    }

    /**
     * Texto con que queda la lectura. Sin desarrollo después de un desarrollo no es el
     * negativo final, y una intermedia sin desarrollo tampoco: tienen texto propio (A6).
     */
    private function label(?string $noNewGrowth, ?string $intermediate, string $optionLabel, ReadingOutcome $outcome, bool $inWorkup, bool $continues): string
    {
        if ($outcome !== ReadingOutcome::NoGrowth) {
            return $optionLabel;
        }

        return match (true) {
            $inWorkup => filled($noNewGrowth) ? (string) $noNewGrowth : PlateObservation::NoNewGrowth->label(),
            $continues => filled($intermediate) ? (string) $intermediate : 'Sin desarrollo hasta el momento',
            default => $optionLabel,
        };
    }

    /**
     * Qué se vio en cada placa que toca leer. Lo que no se indica se completa con lo que
     * dice la opción: sin desarrollo en todas, o desarrollo en las placas de los aislamientos.
     *
     * @param  list<PlateObservationData>  $given
     * @param  list<IsolateData>  $isolates
     * @return Collection<int, PlateObservationData>
     *
     * @throws ValidationException
     */
    private function observations(Culture $culture, ReadingOutcome $outcome, bool $inWorkup, int $stepHours, array $given, array $isolates): Collection
    {
        $due = $culture->plates->filter(fn (Plate $p) => $p->status === PlateStatus::Incubating && $p->isReadAt($stepHours))->keyBy('id');
        $byPlate = collect($given)->keyBy('plateId');

        foreach ($byPlate as $plateId => $observation) {
            if (! $due->has($plateId)) {
                throw ValidationException::withMessages(['plates' => 'Hay una placa que no se lee en esta lectura.']);
            }
            if ($observation->observation === PlateObservation::NoNewGrowth && ! $inWorkup) {
                throw ValidationException::withMessages(['plates' => '«Sin desarrollo nuevo» es solo para un cultivo que ya tiene aislamientos.']);
            }
        }

        $isolatePlates = collect($isolates)->pluck('plateId')->filter()->unique();
        if ($outcome === ReadingOutcome::Growth) {
            foreach ($isolatePlates as $plateId) {
                $observation = $byPlate->get($plateId);
                if ($observation !== null && $observation->observation !== PlateObservation::Growth) {
                    throw ValidationException::withMessages(['plates' => 'Un aislamiento sale de una placa marcada sin desarrollo.']);
                }
            }
            if ($byPlate->isNotEmpty() && ! $byPlate->contains(fn (PlateObservationData $o) => $o->observation === PlateObservation::Growth) && $isolatePlates->isEmpty()) {
                throw ValidationException::withMessages(['plates' => 'Marcá en qué placa hubo desarrollo.']);
            }
        }
        if ($outcome === ReadingOutcome::NoGrowth && $byPlate->contains(fn (PlateObservationData $o) => $o->observation === PlateObservation::Growth)) {
            throw ValidationException::withMessages(['plates' => 'Hay una placa con desarrollo: registrá la lectura con desarrollo.']);
        }

        $default = match (true) {
            $outcome === ReadingOutcome::NoGrowth => $inWorkup ? PlateObservation::NoNewGrowth : PlateObservation::NoGrowth,
            default => null,
        };

        return $due->map(function (Plate $plate) use ($byPlate, $default, $outcome, $isolatePlates) {
            if ($byPlate->has($plate->id)) {
                return $byPlate[$plate->id];
            }
            if ($outcome === ReadingOutcome::Growth && $isolatePlates->contains($plate->id)) {
                return new PlateObservationData($plate->id, PlateObservation::Growth);
            }

            return $default === null ? null : new PlateObservationData($plate->id, $default);
        })->filter()->values();
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
    private function validateOutcome(Culture $culture, ReadingOutcome $outcome, array $isolates, ?string $countCategory): void
    {
        $version = $culture->sampleStudy->protocolVersion;
        $categories = collect($version->count_categories ?? [])->pluck('code');

        if ($outcome === ReadingOutcome::Growth) {
            if ($isolates === []) {
                throw ValidationException::withMessages(['isolates' => 'Cargá al menos un aislamiento.']);
            }
            if (! collect($isolates)->contains(fn (IsolateData $i) => $i->significance() !== IsolateSignificance::NotSignificant)) {
                throw ValidationException::withMessages([
                    'isolates' => 'Ningún aislamiento está marcado como significativo: usá la opción de desarrollo no significativo.',
                ]);
            }
            foreach ($isolates as $index => $isolate) {
                if ($isolate->countCategory !== null && ! $categories->contains($isolate->countCategory)) {
                    throw ValidationException::withMessages(["isolates.{$index}.count_category" => 'Categoría de recuento no válida.']);
                }
            }
        }

        if ($countCategory !== null && ! $categories->contains($countCategory)) {
            throw ValidationException::withMessages(['count_category' => 'Categoría de recuento no válida.']);
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
