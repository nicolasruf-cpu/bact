<?php

namespace App\Modules\Samples\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Configuration\Services\ActiveProtocolResolver;
use App\Modules\Orders\Enums\OrderAuditAction;
use App\Modules\Orders\Models\LabOrder;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Actions\FindOrRegisterPatient;
use App\Modules\Samples\Data\ReceptionData;
use App\Modules\Samples\Enums\SampleAuditAction;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Samples\Models\Reception;
use App\Modules\Samples\Models\Sample;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recepción manual (sin LIS integrado) de una o varias muestras de una orden.
 * - La orden se identifica por su número de LABWIN: si ya existe en la sede, las muestras
 *   se suman a ella (muestras que llegan en distintos momentos); si no, se crea.
 * - Cada muestra nace RECEIVED, con la versión vigente del protocolo de cada estudio congelada,
 *   y emite SampleReceived (→ actividades de siembra).
 * - Todo en una transacción; idempotente por entrega.
 */
class ReceiveOrderSamples
{
    public function __construct(
        private readonly ActiveProtocolResolver $protocols,
        private readonly FindOrRegisterPatient $findOrRegisterPatient,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $receiver, Site $site, ReceptionData $data): Reception
    {
        $existing = Reception::query()->where('idempotency_key', $data->idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $this->ensureBarcodesAreNew($data);
        $protocolVersions = $this->resolveProtocols($data);
        $order = $this->existingOrder($site, $data);

        try {
            return DB::transaction(fn () => $this->receive($receiver, $site, $data, $order, $protocolVersions));
        } catch (UniqueConstraintViolationException) {
            // Dos envíos simultáneos: el índice único frenó al segundo.
            return Reception::query()->where('idempotency_key', $data->idempotencyKey)->first()
                ?? throw ValidationException::withMessages([
                    'samples' => 'Alguna de las muestras ya fue recibida. Revisá los códigos.',
                ]);
        }
    }

    /**
     * @param  array<int, array<int, ProtocolVersion>>  $protocolVersions  índice de muestra => [study_id => versión]
     */
    private function receive(User $receiver, Site $site, ReceptionData $data, ?LabOrder $order, array $protocolVersions): Reception
    {
        $now = Date::now();

        if ($order === null) {
            $patient = $this->findOrRegisterPatient->handle($data->patient);

            $order = LabOrder::create([
                'site_id' => $site->id,
                'patient_id' => $patient->id,
                'external_number' => $data->orderNumber,
                'requesting_service' => $data->requestingService,
                'requesting_physician' => $data->requestingPhysician,
                'priority' => $data->priority,
                'created_by' => $receiver->id,
            ]);

            $this->auditor->record(OrderAuditAction::Created, $order, after: [
                'external_number' => $order->external_number,
                'patient_id' => $patient->id,
                'priority' => $order->priority->value,
            ]);
        }

        $reception = Reception::create([
            'lab_order_id' => $order->id,
            'site_id' => $site->id,
            'received_at' => $now,
            'received_by' => $receiver->id,
            'idempotency_key' => $data->idempotencyKey,
        ]);

        foreach ($data->samples as $index => $incoming) {
            $sample = Sample::create([
                'barcode' => $incoming->barcode,
                'lab_order_id' => $order->id,
                'reception_id' => $reception->id,
                'origin_site_id' => $site->id,
                'sample_type_id' => $incoming->sampleTypeId,
                'status' => SampleStatus::Received,
                'collected_at' => $incoming->collectedAt,
                'received_at' => $now,
                'received_by' => $receiver->id,
                'reception_notes' => $incoming->notes,
            ]);

            foreach ($protocolVersions[$index] as $studyId => $version) {
                $sample->studies()->create([
                    'study_id' => $studyId,
                    'protocol_version_id' => $version->id,
                    'processing_site_id' => $site->id,
                    'status' => SampleStudyStatus::Pending,
                ]);
            }

            $this->auditor->record(SampleAuditAction::Received, $sample, after: [
                'barcode' => $sample->barcode,
                'order_id' => $order->id,
                'sample_type_id' => $sample->sample_type_id,
                'studies' => array_keys($protocolVersions[$index]),
                'protocol_versions' => array_values(array_map(fn (ProtocolVersion $v) => $v->id, $protocolVersions[$index])),
            ], context: ['reception_id' => $reception->id]);

            event(new SampleReceived($sample, $order->priority));
        }

        return $reception;
    }

    /**
     * @throws ValidationException
     */
    private function ensureBarcodesAreNew(ReceptionData $data): void
    {
        $errors = [];
        $seen = [];

        foreach ($data->samples as $index => $sample) {
            if (isset($seen[$sample->barcode])) {
                $errors["samples.{$index}.barcode"] = "El código {$sample->barcode} está repetido en esta recepción.";
            }
            $seen[$sample->barcode] = true;
        }

        $alreadyReceived = Sample::query()->whereIn('barcode', array_keys($seen))->pluck('barcode')->all();
        foreach ($data->samples as $index => $sample) {
            if (in_array($sample->barcode, $alreadyReceived, true)) {
                $errors["samples.{$index}.barcode"] = "La muestra {$sample->barcode} ya fue recibida.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Cada estudio de cada muestra debe tener protocolo vigente para su tipo de muestra.
     *
     * @return array<int, array<int, ProtocolVersion>>
     *
     * @throws ValidationException
     */
    private function resolveProtocols(ReceptionData $data): array
    {
        $versions = [];
        $errors = [];

        foreach ($data->samples as $index => $sample) {
            $sampleType = SampleType::query()->findOrFail($sample->sampleTypeId);
            $missing = [];

            foreach (array_unique($sample->studyIds) as $studyId) {
                $version = $this->protocols->resolve($studyId, $sampleType->id);

                if ($version === null) {
                    $missing[] = Study::query()->find($studyId)->name ?? "#{$studyId}";

                    continue;
                }

                $versions[$index][$studyId] = $version;
            }

            if ($missing !== []) {
                $errors["samples.{$index}.study_ids"] = 'No hay protocolo vigente para '.implode(', ', $missing)
                    ." en {$sampleType->name}. El Director técnico responsable tiene que aprobar uno.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $versions;
    }

    /**
     * @throws ValidationException
     */
    private function existingOrder(Site $site, ReceptionData $data): ?LabOrder
    {
        if ($data->orderNumber === null) {
            return null;
        }

        $order = LabOrder::query()
            ->where('site_id', $site->id)
            ->where('external_number', $data->orderNumber)
            ->with('patient')
            ->first();

        if ($order !== null && $order->patient->document_number !== $data->patient->documentNumber) {
            throw ValidationException::withMessages([
                'document_number' => "La orden {$data->orderNumber} es de otro paciente ({$order->patient->fullName()}).",
            ]);
        }

        return $order;
    }
}
