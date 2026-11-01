<?php

namespace App\Modules\Samples\Queries;

use App\Modules\Samples\Models\SampleStudy;

/**
 * Identidad de la muestra que se muestra fija arriba en cada pantalla de trabajo (UX_UI.md).
 */
class SampleHeader
{
    /**
     * @return array<string, mixed>
     */
    public function for(SampleStudy $study): array
    {
        $study->loadMissing(['sample.labOrder.patient', 'sample.sampleType', 'study', 'protocolVersion.protocol']);
        $sample = $study->sample;
        $order = $sample->labOrder;

        return [
            'sample_study_id' => $study->id,
            'barcode' => $sample->barcode,
            'patient' => $order->patient->fullName(),
            'document_number' => $order->patient->document_number,
            'order_number' => $order->external_number,
            'physician' => $order->requesting_physician,
            'priority' => $order->priority->value,
            'priority_label' => $order->priority->label(),
            'sample_type' => $sample->sampleType->name,
            'collected_at' => $sample->collected_at?->toIso8601String(),
            'received_at' => $sample->received_at->toIso8601String(),
            'study' => $study->study->name,
            'study_status' => $study->status->label(),
            'protocol' => $study->protocolVersion->protocol->name,
            'protocol_version' => $study->protocolVersion->version,
            'is_reference_template' => $study->protocolVersion->is_reference_template,
        ];
    }
}
