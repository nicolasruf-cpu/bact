<?php

namespace App\Modules\Reporting\Services;

use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Queries\OrderBloodCultureSummary;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Queries\DirectExamQuery;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Identification\Models\Referral;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;

/**
 * Arma el contenido del informe a partir de los datos registrados. No agrega texto clínico
 * propio: usa la redacción de las opciones del protocolo y los nombres de los catálogos.
 */
class ReportBuilder
{
    public function __construct(
        private readonly OrderBloodCultureSummary $bloodSummary,
        private readonly DirectExamQuery $directExam,
    ) {}

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
            ->latest('id')
            ->first();

        // Resultado: la lectura con desarrollo si la hubo (las siguientes registran "sin desarrollo
        // nuevo", D-0017); si no, la última lectura.
        /** @var Reading|null $finalReading */
        $finalReading = $culture?->readings->first(fn (Reading $r) => $r->outcome === ReadingOutcome::Growth)
            ?? $culture?->readings->last();
        $options = collect($version->reading_options ?? [])->keyBy('code');
        $counts = collect($version->count_categories ?? [])->keyBy('code');

        $result = $finalReading ? ($finalReading->label ?? $options[$finalReading->option_code]['label'] ?? $finalReading->option_code) : null;
        // Preliminar con el cultivo abierto y sin desarrollo todavía: no se adelanta un negativo.
        if (! $version->isBloodCulture() && $culture !== null && ! $culture->status->isClosed() && $finalReading?->outcome !== ReadingOutcome::Growth) {
            $result = 'Cultivo en curso';
        }
        $bloodCulture = $version->isBloodCulture() ? $this->bloodCulture($study, $order->id) : null;
        if ($bloodCulture !== null && $result === null) {
            $result = $bloodCulture['result'];
        }
        // Muestra no apta (B8): el resultado es el motivo, con la nueva muestra pedida.
        $rejection = $study->rejected_at === null ? null : [
            'reason' => $study->rejection_reason,
            'new_sample_requested_to' => $study->new_sample_requested_to,
            'rejected_at' => $study->rejected_at->toIso8601String(),
        ];
        if ($rejection !== null) {
            $result = 'Muestra no apta: '.$study->rejection_reason;
        }
        $isolates = $culture === null ? collect() : $culture->isolates;

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
            'direct_exam' => $this->directExam->forStudy($study),
            'result' => $result,
            'blood_culture' => $bloodCulture,
            'reading_notes' => $finalReading?->notes,
            'result_count' => $finalReading?->count_category ? ($counts[$finalReading->count_category]['label'] ?? $finalReading->count_category) : null,
            'rejection' => $rejection,
            'isolates' => $isolates
                ->filter(fn (Isolate $i) => $i->significance === IsolateSignificance::Significant)
                ->map(fn (Isolate $isolate) => $this->isolate($isolate, $counts->all()))
                ->values()
                ->all(),
            // Otros desarrollos (C1, C3): no significativos y probables contaminantes, si el protocolo los informa.
            'other_isolates' => ! $version->report_non_significant || $rejection !== null ? [] : $isolates
                ->filter(fn (Isolate $i) => $i->significance !== IsolateSignificance::Significant)
                ->map(fn (Isolate $i) => [
                    'number' => $i->number,
                    'organism' => $i->organism?->name,
                    'description' => $i->organism === null ? $i->morphotype : null,
                    'count' => $i->count_category ? ($counts[$i->count_category]['label'] ?? $i->count_category) : null,
                    'significance' => $i->significance->value,
                    'significance_label' => $i->significance->label(),
                ])
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
            ->current()
            ->with(['results.antibiotic.reportedAs', 'breakpointSet', 'panel'])
            ->latest('id')
            ->first();

        return [
            'number' => $isolate->number,
            'organism' => $isolate->organism?->name,
            'count' => $isolate->count_category ? ($counts[$isolate->count_category]['label'] ?? $isolate->count_category) : null,
            'identification_method' => $identification?->method->label(),
            'no_ast_reason' => $isolate->no_ast_reason,
            // Pruebas que el laboratorio marcó para informar (p. ej., BLEE, carbapenemasa) (B5).
            'tests' => IsolateTest::query()->where('isolate_id', $isolate->id)->whereNotNull('read_at')
                ->whereHas('test', fn ($q) => $q->where('is_reportable', true))->with('test')->orderBy('read_at')->get()
                ->map(fn (IsolateTest $t) => ['name' => $t->test->name, 'result' => $t->result_label])
                ->values()->all(),
            'referrals' => Referral::query()->where('isolate_id', $isolate->id)->orderBy('sent_at')->get()
                ->map(fn (Referral $r) => [
                    'destination' => $r->destination,
                    'reason' => $r->reason,
                    'result' => $r->result,
                    'result_at' => $r->result_at?->toIso8601String(),
                ])->values()->all(),
            // Solo lo leído; en el preliminar se avisa cuántos antibióticos faltan (D-0022).
            'susceptibility' => $test === null ? null : [
                'method' => $test->results->map(fn (SusceptibilityResult $r) => $r->method)->unique()
                    ->map(fn (AstMethod $m) => $m->label())->implode(' + ').($test->panel->is_vitek ? ' · VITEK' : ''),
                'unit' => $test->method->unit(),
                'pending' => $test->pendingResults()->count(),
                'breakpoints' => $test->breakpointSet ? [
                    'name' => $test->breakpointSet->name,
                    'edition' => $test->breakpointSet->edition,
                    'is_synthetic' => $test->breakpointSet->is_synthetic,
                ] : null,
                'results' => $test->results
                    ->filter(fn (SusceptibilityResult $r) => $r->is_reported && $r->isRead())
                    ->map(fn (SusceptibilityResult $r) => [
                        // Prueba subrogante (A7): se informa el antibiótico que representa.
                        'antibiotic' => $r->antibiotic->reportedAs !== null
                            ? $r->antibiotic->reportedAs->name.' (por '.$r->antibiotic->displayName().')'
                            : $r->antibiotic->displayName(),
                        'comment' => $r->rule_notes,
                        'unit' => $r->method->unit(),
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
