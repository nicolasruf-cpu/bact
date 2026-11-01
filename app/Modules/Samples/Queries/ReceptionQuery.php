<?php

namespace App\Modules\Samples\Queries;

use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Patients\Models\Patient;
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
     * Muestras recibidas hoy en la sede.
     *
     * @return array<int, array<string, mixed>>
     */
    public function receivedToday(int $siteId): array
    {
        return Sample::query()
            ->where('origin_site_id', $siteId)
            ->where('received_at', '>=', Date::now()->startOfDay()->utc())
            ->with(['labOrder.patient', 'sampleType', 'studies.study'])
            ->orderByDesc('received_at')
            ->limit(50)
            ->get()
            ->map(fn (Sample $sample) => [
                'ulid' => $sample->ulid,
                'barcode' => $sample->barcode,
                'patient' => $sample->labOrder->patient->fullName(),
                'sample_type' => $sample->sampleType->name,
                'studies' => $sample->studies->map(fn ($s) => $s->study->name)->values()->all(),
                'priority' => $sample->labOrder->priority->value,
                'received_at' => $sample->received_at->toIso8601String(),
                'status' => $sample->status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{document_number: string, last_name: string, first_name: string, birth_date: string|null, sex: string}|null
     */
    public function patientByDocument(?string $document): ?array
    {
        if ($document === null || $document === '') {
            return null;
        }

        $patient = Patient::query()->where('document_number', strtoupper(trim($document)))->first();

        return $patient ? [
            'document_number' => $patient->document_number,
            'last_name' => $patient->last_name,
            'first_name' => $patient->first_name,
            'birth_date' => $patient->birth_date?->toDateString(),
            'sex' => $patient->sex->value,
        ] : null;
    }
}
