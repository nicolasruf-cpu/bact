<?php

namespace App\Modules\Reporting\Services;

use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Queries\OrderBloodCultureSummary;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Models\Identification;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;

/**
 * Arma el contenido del informe a partir de los datos registrados. No agrega texto clínico
 * propio: usa la redacción de las opciones del protocolo y los nombres de los catálogos.
 */
class ReportBuilder
{
    public function __construct(private readonly OrderBloodCultureSummary $bloodSummary) {}

    /**
     * @return array<string, mixed>
     */
    public function build(SampleStudy $study): array
    {
        $study->load([
            'sample.labOrder.patient', 'sample.sampleType', 'sample.originSite', 'study', 'protocolVersion.protocol',
        ]);
        $sample = $study->sample;
        $order = $sample->labOrder;
        $patient = $order->patient;
        $version = $study->protocolVersion;

        /** @var Culture|null $culture */
        $culture = Culture::query()
            ->where('sample_study_id', $study->id)
            ->with(['readings', 'isolates.organism', 'isolates.plate.medium'])
            ->first();

        /** @var Reading|null $finalReading */
        $finalReading = $culture?->readings->last();
        $options = collect($version->reading_options ?? [])->keyBy('code');
        $counts = collect($version->count_categories ?? [])->keyBy('code');

        $result = $finalReading ? ($options[$finalReading->option_code]['label'] ?? $finalReading->option_code) : null;
        $bloodCulture = $version->isBloodCulture() ? $this->bloodCulture($study, $order->id) : null;
        if ($bloodCulture !== null && $result === null) {
            $result = $bloodCulture['result'];
        }

        return [
            'kind' => $version->processing_kind->value,
            'site' => $sample->originSite->name,
            'patient' => [
                'name' => $patient->fullName(),
                'document_number' => $patient->document_number,
                'birth_date' => $patient->birth_date?->toDateString(),
                'sex' => $patient->sex->label(),
            ],
            'order' => [
                'number' => $order->external_number,
                'physician' => $order->requesting_physician,
                'service' => $order->requesting_service,
                'priority' => $order->priority->label(),
            ],
            'sample' => [
                'barcode' => $sample->barcode,
                'type' => $sample->sampleType->name,
                'collected_at' => $sample->collected_at?->toIso8601String(),
                'received_at' => $sample->received_at->toIso8601String(),
            ],
            'study' => $study->study->name,
            'protocol' => [
                'name' => $version->protocol->name,
                'version' => $version->version,
                'is_reference_template' => $version->is_reference_template,
            ],
            'result' => $result,
            'blood_culture' => $bloodCulture,
            'reading_notes' => $finalReading?->notes,
            'isolates' => ($culture === null ? collect() : $culture->isolates)
                ->filter(fn (Isolate $i) => $i->is_significant)
                ->map(fn (Isolate $isolate) => $this->isolate($isolate, $counts->all()))
                ->values()
                ->all(),
        ];
    }

    /**
     * Frasco, Gram y resumen de los hemocultivos de la orden al momento del informe.
     *
     * @return array<string, mixed>|null
     */
    private function bloodCulture(SampleStudy $study, int $orderId): ?array
    {
        /** @var BloodCultureBottle|null $bottle */
        $bottle = BloodCultureBottle::query()->where('sample_study_id', $study->id)->with(['gramStain', 'sampleStudy.protocolVersion'])->first();
        if ($bottle === null) {
            return null;
        }

        $settings = $bottle->settings();
        $gram = $bottle->gramStain;

        return [
            'method' => $bottle->method->label(),
            'bottle_type' => $settings->bottleTypeLabel($bottle->bottle_type),
            'instrument' => $bottle->instrument,
            'loaded_at' => $bottle->loaded_at->toIso8601String(),
            'incubation_days' => $bottle->incubationDays(),
            'status' => $bottle->status->label(),
            'positive_at' => $bottle->positive_at?->toIso8601String(),
            'positivity_source' => $bottle->positivity_source?->label(),
            'minutes_to_positivity' => $bottle->minutesToPositivity(),
            'gram' => $gram === null ? null : [
                'result' => $settings->gramLabel($gram->option_code),
                'description' => $gram->description,
                'performed_at' => $gram->performed_at->toIso8601String(),
            ],
            'result' => match ($bottle->status) {
                BottleStatus::Negative => $settings->negativeResult($bottle->incubationDays()),
                BottleStatus::Positive => 'Frasco positivo: estudio en curso',
                BottleStatus::Incubating => 'En incubación',
            },
            'order_summary' => $this->bloodSummary->forOrder($orderId),
        ];
    }

    /**
     * @param  array<string, array{code: string, label: string}>  $counts
     * @return array<string, mixed>
     */
    private function isolate(Isolate $isolate, array $counts): array
    {
        /** @var Identification|null $identification */
        $identification = Identification::query()
            ->where('isolate_id', $isolate->id)
            ->where('level', IdentificationLevel::Definitive->value)
            ->latest('id')
            ->first();

        /** @var SusceptibilityTest|null $test */
        $test = SusceptibilityTest::query()
            ->where('isolate_id', $isolate->id)
            ->with(['results.antibiotic', 'breakpointSet', 'panel'])
            ->first();

        return [
            'number' => $isolate->number,
            'organism' => $isolate->organism?->name,
            'count' => $isolate->count_category ? ($counts[$isolate->count_category]['label'] ?? $isolate->count_category) : null,
            'identification_method' => $identification?->method->label(),
            'no_ast_reason' => $isolate->no_ast_reason,
            'susceptibility' => $test === null ? null : [
                'method' => $test->method->label(),
                'unit' => $test->method->unit(),
                'breakpoints' => $test->breakpointSet ? [
                    'name' => $test->breakpointSet->name,
                    'edition' => $test->breakpointSet->edition,
                    'is_synthetic' => $test->breakpointSet->is_synthetic,
                ] : null,
                'results' => $test->results
                    ->filter(fn (SusceptibilityResult $r) => $r->is_reported)
                    ->map(fn (SusceptibilityResult $r) => [
                        'antibiotic' => $r->antibiotic->name,
                        'value' => $r->measured_value === null ? null : trim(($r->mic_operator && $r->mic_operator !== '=' ? $r->mic_operator.' ' : '').rtrim(rtrim($r->measured_value, '0'), '.')),
                        'interpretation' => $r->finalInterpretation()?->value,
                        'interpretation_label' => $r->finalInterpretation()?->label(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
