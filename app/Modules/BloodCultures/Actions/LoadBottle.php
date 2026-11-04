<?php

namespace App\Modules\BloodCultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Events\BottleLoaded;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga del frasco: en el equipo (se registra lo que informa el equipo) o en estufa para
 * procesamiento manual con repiques. Los días de incubación salen del protocolo congelado.
 * Idempotente: repetir la misma carga devuelve el frasco; otro frasco en la misma muestra es un error.
 */
class LoadBottle
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(
        User $user,
        SampleStudy $study,
        BottleMethod $method,
        string $bottleType,
        ?string $instrument = null,
        ?string $position = null,
        ?CarbonInterface $loadedAt = null,
    ): BloodCultureBottle {
        if (! $user->can(Permission::BloodCulturesLoad->value)) {
            throw new AuthorizationException('No tenés permiso para cargar frascos.');
        }

        return DB::transaction(function () use ($user, $study, $method, $bottleType, $instrument, $position, $loadedAt) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->with(['protocolVersion', 'sample'])->findOrFail($study->id);

            // Reintento de la misma carga: devuelve el frasco. Otro frasco (p. ej., el anaerobio del
            // set) es otra muestra con su código: no se ignora sin avisar (revisión de marchas A5).
            $existing = BloodCultureBottle::query()->where('sample_study_id', $study->id)->first();
            if ($existing !== null) {
                if ($existing->bottle_type === $bottleType && $existing->method === $method) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'bottle_type' => 'Esta muestra ya tiene un frasco cargado ('.$existing->settings()->bottleTypeLabel($existing->bottle_type).'). Cada frasco del set se recibe como una muestra propia, con su código.',
                ]);
            }

            $settings = $study->protocolVersion->bloodCultureSettings();
            if (! $study->protocolVersion->isBloodCulture() || $settings === null) {
                throw ValidationException::withMessages(['study' => 'Este estudio no se procesa como frasco de hemocultivo.']);
            }
            if ($study->status !== SampleStudyStatus::Pending) {
                throw ValidationException::withMessages(['study' => 'Este estudio no está pendiente de carga.']);
            }
            if (! collect($settings->bottleTypes)->contains('code', $bottleType)) {
                throw ValidationException::withMessages(['bottle_type' => 'Tipo de frasco no válido para este protocolo.']);
            }

            $automated = $method === BottleMethod::Automated;
            if ($automated && ! in_array($instrument, $settings->instruments, true)) {
                throw ValidationException::withMessages(['instrument' => 'Elegí el equipo donde se cargó el frasco.']);
            }

            $loadedAt ??= Date::now();
            if ($loadedAt->isFuture()) {
                throw ValidationException::withMessages(['loaded_at' => 'La hora de carga no puede ser futura.']);
            }
            if ($loadedAt->lessThan($study->sample->received_at)) {
                throw ValidationException::withMessages(['loaded_at' => 'La hora de carga no puede ser anterior a la recepción.']);
            }

            $days = $settings->incubationDays($automated);

            $bottle = BloodCultureBottle::create([
                'sample_study_id' => $study->id,
                'method' => $method,
                'bottle_type' => $bottleType,
                'instrument' => $automated ? $instrument : null,
                'instrument_position' => $automated ? $position : null,
                'loaded_at' => $loadedAt,
                'loaded_by' => $user->id,
                'incubation_ends_at' => $loadedAt->copy()->addDays($days),
                'status' => BottleStatus::Incubating,
            ]);

            $study->update(['status' => SampleStudyStatus::InProcess, 'lock_version' => $study->lock_version + 1]);
            if ($study->sample->status === SampleStatus::Received) {
                $study->sample->update(['status' => SampleStatus::InProcess, 'lock_version' => $study->sample->lock_version + 1]);
            }

            $this->auditor->record(BloodCultureAuditAction::BottleLoaded, $bottle, after: [
                'sample_study_id' => $study->id,
                'method' => $method->value,
                'bottle_type' => $bottleType,
                'instrument' => $bottle->instrument,
                'position' => $bottle->instrument_position,
                'loaded_at' => $loadedAt->toIso8601String(),
                'incubation_days' => $days,
            ]);

            event(new BottleLoaded($bottle, $study, $user->id));

            return $bottle;
        });
    }
}
