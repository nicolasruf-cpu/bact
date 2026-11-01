<?php

namespace App\Modules\Samples\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Configuration\Services\ActiveProtocolResolver;
use App\Modules\Orders\Models\LabOrder;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Actions\FindOrRegisterPatient;
use App\Modules\Samples\Data\ReceiveSampleData;
use App\Modules\Samples\Enums\SampleAuditAction;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Events\SampleReceived;
use App\Modules\Samples\Models\Sample;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recepción manual (sin LIS integrado): se escanea la etiqueta del LIS y se registran
 * paciente, tipo de muestra y estudios. Crea la muestra en RECEIVED, congela la versión
 * vigente del protocolo de cada estudio y emite SampleReceived (→ actividades de siembra).
 */
class ReceiveSample
{
    public function __construct(
        private readonly ActiveProtocolResolver $protocols,
        private readonly FindOrRegisterPatient $findOrRegisterPatient,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $receiver, Site $site, ReceiveSampleData $data): Sample
    {
        // Idempotencia: un reintento o doble clic devuelve la misma muestra.
        $existing = Sample::query()->where('idempotency_key', $data->idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (Sample::query()->where('barcode', $data->barcode)->exists()) {
            throw ValidationException::withMessages([
                'barcode' => "La muestra {$data->barcode} ya fue recibida.",
            ]);
        }

        $protocolVersions = $this->resolveProtocols($data);

        try {
            return $this->receive($receiver, $site, $data, $protocolVersions);
        } catch (UniqueConstraintViolationException $e) {
            // Dos envíos simultáneos: el índice único frenó al segundo.
            $sample = Sample::query()->where('idempotency_key', $data->idempotencyKey)->first();
            if ($sample !== null) {
                return $sample;
            }

            throw ValidationException::withMessages([
                'barcode' => "La muestra {$data->barcode} ya fue recibida.",
            ]);
        }
    }

    /**
     * @param  array<int, ProtocolVersion>  $protocolVersions
     */
    private function receive(User $receiver, Site $site, ReceiveSampleData $data, array $protocolVersions): Sample
    {
        return DB::transaction(function () use ($receiver, $site, $data, $protocolVersions) {
            $patient = $this->findOrRegisterPatient->handle($data->patient);

            $order = LabOrder::create([
                'site_id' => $site->id,
                'patient_id' => $patient->id,
                'requesting_service' => $data->requestingService,
                'requesting_physician' => $data->requestingPhysician,
                'priority' => $data->priority,
                'created_by' => $receiver->id,
            ]);

            $sample = Sample::create([
                'barcode' => $data->barcode,
                'lab_order_id' => $order->id,
                'origin_site_id' => $site->id,
                'sample_type_id' => $data->sampleTypeId,
                'status' => SampleStatus::Received,
                'collected_at' => $data->collectedAt,
                'received_at' => Date::now(),
                'received_by' => $receiver->id,
                'reception_notes' => $data->notes,
                'idempotency_key' => $data->idempotencyKey,
            ]);

            foreach ($protocolVersions as $studyId => $version) {
                $sample->studies()->create([
                    'study_id' => $studyId,
                    'protocol_version_id' => $version->id,
                    'processing_site_id' => $site->id,
                    'status' => SampleStudyStatus::Pending,
                ]);
            }

            $this->auditor->record(SampleAuditAction::Received, $sample, after: [
                'barcode' => $sample->barcode,
                'sample_type_id' => $sample->sample_type_id,
                'studies' => array_keys($protocolVersions),
                'protocol_versions' => array_values(array_map(fn (ProtocolVersion $v) => $v->id, $protocolVersions)),
                'priority' => $data->priority->value,
            ]);

            event(new SampleReceived($sample, $data->priority));

            return $sample;
        });
    }

    /**
     * Cada estudio debe tener un protocolo vigente para el tipo de muestra.
     *
     * @return array<int, ProtocolVersion> study_id => versión vigente
     *
     * @throws ValidationException
     */
    private function resolveProtocols(ReceiveSampleData $data): array
    {
        $sampleType = SampleType::query()->findOrFail($data->sampleTypeId);
        $versions = [];
        $missing = [];

        foreach (array_unique($data->studyIds) as $studyId) {
            $version = $this->protocols->resolve($studyId, $sampleType->id);

            if ($version === null) {
                $missing[] = Study::query()->find($studyId)->name ?? "#{$studyId}";

                continue;
            }

            $versions[$studyId] = $version;
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'study_ids' => 'No hay protocolo vigente para '.implode(', ', $missing)." en {$sampleType->name}. "
                    .'El Director técnico responsable tiene que aprobar uno.',
            ]);
        }

        return $versions;
    }
}
