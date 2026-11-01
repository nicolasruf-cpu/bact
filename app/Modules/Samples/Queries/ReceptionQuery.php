<?php

namespace App\Modules\Samples\Queries;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Orders\Models\LabOrder;
use App\Modules\Patients\Models\Patient;
use App\Modules\Samples\Models\Reception;
use App\Modules\Samples\Models\Sample;
use Illuminate\Support\Facades\Date;

/**
 * Datos de lectura para la pantalla de recepción.
 */
class ReceptionQuery
{
    /**
     * Tipos de muestra y estudios activos, con qué combinaciones tienen protocolo vigente.
     *
     * @return array{sampleTypes: array<int, array{id: int, code: string, name: string}>, studies: array<int, array{id: int, code: string, name: string}>, available: array<int, list<int>>, referenceTemplates: list<int>}
     */
    public function catalogs(): array
    {
        $activeProtocols = Protocol::query()
            ->whereHas('versions', fn ($q) => $q->where('status', ProtocolVersionStatus::Active->value))
            ->with('activeVersion:id,protocol_id,is_reference_template')
            ->get();

        $available = [];
        $referenceTemplates = [];
        foreach ($activeProtocols as $protocol) {
            $available[$protocol->sample_type_id][] = $protocol->study_id;
            if ($protocol->activeVersion?->is_reference_template) {
                $referenceTemplates[] = $protocol->sample_type_id;
            }
        }

        return [
            'sampleTypes' => SampleType::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (SampleType $t) => ['id' => $t->id, 'code' => $t->code, 'name' => $t->name])->values()->all(),
            'studies' => Study::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn (Study $s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->values()->all(),
            'available' => $available,
            'referenceTemplates' => array_values(array_unique($referenceTemplates)),
        ];
    }

    /**
     * Entregas recibidas hoy en la sede, con sus muestras.
     *
     * @return array<int, array<string, mixed>>
     */
    public function receivedToday(int $siteId): array
    {
        return Reception::query()
            ->where('site_id', $siteId)
            ->where('received_at', '>=', Date::now()->startOfDay()->utc())
            ->with(['labOrder.patient', 'samples.sampleType', 'samples.studies.study'])
            ->orderByDesc('received_at')
            ->limit(50)
            ->get()
            ->map(fn (Reception $reception) => [
                'ulid' => $reception->ulid,
                'order_number' => $reception->labOrder->external_number,
                'patient' => $reception->labOrder->patient->fullName(),
                'priority' => $reception->labOrder->priority->value,
                'received_at' => $reception->received_at->toIso8601String(),
                'samples' => $reception->samples->map(fn (Sample $sample) => $this->sampleSummary($sample))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Orden existente de la sede por su número de LABWIN, con las muestras ya recibidas.
     *
     * @return array<string, mixed>|null
     */
    public function orderByNumber(int $siteId, ?string $number): ?array
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $order = LabOrder::query()
            ->where('site_id', $siteId)
            ->where('external_number', strtoupper(trim($number)))
            ->with('patient')
            ->first();

        if ($order === null) {
            return null;
        }

        $samples = Sample::query()
            ->where('lab_order_id', $order->id)
            ->with(['sampleType', 'studies.study'])
            ->orderBy('received_at')
            ->get();

        return [
            'order_number' => $order->external_number,
            'patient' => $this->patientSummary($order->patient),
            'requesting_service' => $order->requesting_service,
            'requesting_physician' => $order->requesting_physician,
            'priority' => $order->priority->value,
            'samples' => $samples->map(fn (Sample $sample) => $this->sampleSummary($sample))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleSummary(Sample $sample): array
    {
        return [
            'ulid' => $sample->ulid,
            'barcode' => $sample->barcode,
            'sample_type' => $sample->sampleType->name,
            'studies' => $sample->studies->map(fn ($s) => $s->study->name)->values()->all(),
            'received_at' => $sample->received_at->toIso8601String(),
            'status' => $sample->status->label(),
        ];
    }

    /**
     * @return array{document_number: string, last_name: string, first_name: string, birth_date: string|null, sex: string}|null
     */
    public function patientByDocument(?string $document): ?array
    {
        if ($document === null || $document === '') {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[\s.\-]/', '', $document));
        $patient = Patient::query()->where('document_number', $normalized)->first();

        return $patient ? $this->patientSummary($patient) : null;
    }

    /**
     * @return array{document_number: string, last_name: string, first_name: string, birth_date: string|null, sex: string}
     */
    private function patientSummary(Patient $patient): array
    {
        return [
            'document_number' => $patient->document_number,
            'last_name' => $patient->last_name,
            'first_name' => $patient->first_name,
            'birth_date' => $patient->birth_date?->toDateString(),
            'sex' => $patient->sex->value,
        ];
    }
}
