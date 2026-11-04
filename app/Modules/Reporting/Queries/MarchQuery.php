<?php

namespace App\Modules\Reporting\Queries;

use App\Models\User;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\PlateReading;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Queries\DirectExamQuery;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identification\Models\InstrumentRun;
use App\Modules\Identification\Models\IsolateSubculture;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Identification\Models\Referral;
use App\Modules\Reporting\Models\Report;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Carbon\CarbonInterface;

/**
 * Reconstrucción de la marcha de un estudio solo con lo registrado (revisión de marchas):
 * en qué se sembró, qué se vio en cada lectura y en cada placa, el Gram y las pruebas de cada
 * aislamiento, el equipo, la identificación, el antibiograma con su método, valores, puntos
 * de corte y reglas, las derivaciones y los informes. Cada paso con su hora y quién lo hizo.
 */
class MarchQuery
{
    /** @var array<int, string> */
    private array $users = [];

    public function __construct(private readonly DirectExamQuery $directExam) {}

    /**
     * @return array<string, mixed>
     */
    public function forStudy(SampleStudy $study): array
    {
        $study->load(['sample.sampleType', 'study', 'protocolVersion.protocol']);
        $version = $study->protocolVersion;
        $options = collect($version->reading_options ?? [])->keyBy('code');
        $counts = collect($version->count_categories ?? [])->keyBy('code');

        $culture = Culture::query()->where('sample_study_id', $study->id)
            ->with(['plates.medium', 'readings.plateReadings', 'isolates.organism', 'isolates.plate.medium'])
            ->latest('id')->first();
        $bottle = BloodCultureBottle::query()->where('sample_study_id', $study->id)->with(['gramStain', 'blindSubcultures.medium'])->first();

        $steps = collect();
        $steps->push($this->step($study->sample->received_at, 'Recepción', $study->sample->sampleType->name.' · '.$version->protocol->name.' v'.$version->version, null));

        $direct = $this->directExam->forStudy($study);
        if ($direct !== null) {
            $steps->push($this->step($direct['performed_at'], $direct['label'], collect($direct['lines'])->map(fn (array $l) => $l['field'].': '.implode(', ', $l['values']))->implode(' · ').($direct['description'] ? ' — '.$direct['description'] : ''), null, $direct['performed_by']));
        }

        if ($bottle !== null) {
            $settings = $bottle->settings();
            $steps->push($this->step($bottle->loaded_at, 'Carga del frasco', $settings->bottleTypeLabel($bottle->bottle_type).' · '.$bottle->method->label().($bottle->instrument ? ' ('.$bottle->instrument.')' : ''), $bottle->loaded_by));
            foreach ($bottle->blindSubcultures as $blind) {
                /** @var BlindSubculture $blind */
                $steps->push($this->step($blind->subcultured_at, 'Repique a ciegas n.º '.$blind->number, $blind->medium->name, $blind->subcultured_by));
                if ($blind->read_at !== null) {
                    $steps->push($this->step($blind->read_at, 'Lectura del repique n.º '.$blind->number, ($blind->result?->label() ?? '').($blind->notes ? ' — '.$blind->notes : ''), $blind->read_by));
                }
            }
            if ($bottle->positive_at !== null) {
                $steps->push($this->step($bottle->positive_at, 'Frasco positivo', $bottle->positivity_source?->label() ?? '', $bottle->positive_recorded_by));
            }
            if ($bottle->gramStain !== null) {
                $steps->push($this->step($bottle->gramStain->performed_at, 'Gram del frasco', $settings->gramLabel($bottle->gramStain->option_code).($bottle->gramStain->description ? ' — '.$bottle->gramStain->description : ''), $bottle->gramStain->performed_by));
            }
        }

        if ($culture !== null) {
            $primary = $culture->plates->filter(fn (Plate $p) => $p->source_plate_id === null && $p->replaces_plate_id === null && $p->source_isolate_id === null);
            $steps->push($this->step($culture->inoculated_at, $bottle !== null ? 'Subcultivo del frasco' : 'Siembra', $primary->map(fn (Plate $p) => $this->plateLabel($p))->implode(' · ').($culture->notes ? ' — '.$culture->notes : ''), $culture->inoculated_by));

            foreach ($culture->plates->filter(fn (Plate $p) => $p->source_plate_id !== null) as $plate) {
                $steps->push($this->step($plate->inoculated_at, 'Repique del caldo', $this->plateLabel($plate), null));
            }
            foreach ($culture->plates->filter(fn (Plate $p) => $p->lost_at !== null) as $plate) {
                $steps->push($this->step($plate->lost_at, 'Placa perdida', $plate->medium->name.': '.$plate->lost_reason, $plate->lost_by));
            }
            foreach ($culture->plates->filter(fn (Plate $p) => $p->replaces_plate_id !== null) as $plate) {
                $steps->push($this->step($plate->inoculated_at, 'Resiembra', $this->plateLabel($plate), null));
            }

            foreach ($culture->readings as $reading) {
                /** @var Reading $reading */
                $plates = $reading->plateReadings->map(fn (PlateReading $pr) => ($culture->plates->firstWhere('id', $pr->plate_id)?->medium->name ?? '').': '.mb_strtolower($pr->observation->label()).($pr->notes ? ' ('.$pr->notes.')' : ''))->implode(' · ');
                $label = $reading->label ?? $options[$reading->option_code]['label'] ?? $reading->option_code;
                $count = $reading->count_category ? ' · '.($counts[$reading->count_category]['label'] ?? $reading->count_category) : '';
                $steps->push($this->step($reading->read_at, 'Lectura n.º '.$reading->attempt, $label.$count.($plates !== '' ? ' — '.$plates : '').($reading->notes ? ' — '.$reading->notes : ''), $reading->read_by));
            }
        }

        if ($study->rejected_at !== null) {
            $steps->push($this->step($study->rejected_at, 'Muestra no apta', $study->rejection_reason.($study->new_sample_requested_to ? ' · Nueva muestra pedida a '.$study->new_sample_requested_to : ''), $study->rejected_by));
        }

        foreach (Report::query()->where('sample_study_id', $study->id)->orderBy('version')->get() as $report) {
            $steps->push($this->step($report->validated_at, 'Informe '.$report->type->label().' v'.$report->version, $report->status->label(), $report->validated_by));
        }

        return [
            'study' => [
                'id' => $study->id,
                'name' => $study->study->name,
                'protocol' => $version->protocol->name.' v'.$version->version,
                'status' => $study->status->label(),
            ],
            'timeline' => $steps->sortBy('sort')->values()->map(fn (array $s) => array_diff_key($s, ['sort' => true]))->all(),
            'isolates' => $culture === null ? [] : $culture->isolates->map(fn (Isolate $i) => $this->isolate($i, $counts->all()))->values()->all(),
        ];
    }

    /**
     * La marcha de un aislamiento, en el orden en que se trabaja.
     *
     * @param  array<string, array{code: string, label: string}>  $counts
     * @return array<string, mixed>
     */
    private function isolate(Isolate $isolate, array $counts): array
    {
        $steps = collect();

        foreach (IsolateSubculture::query()->where('isolate_id', $isolate->id)->with('plate.medium')->get() as $sub) {
            $steps->push($this->step($sub->subcultured_at, 'Repique del aislamiento', $this->plateLabel($sub->plate).($sub->reason ? ' — '.$sub->reason : ''), $sub->subcultured_by));
            if ($sub->read_at !== null) {
                $steps->push($this->step($sub->read_at, 'Lectura del repique', ($sub->result?->label() ?? '').($sub->notes ? ' — '.$sub->notes : ''), $sub->read_by));
            }
        }

        foreach (IsolateTest::query()->where('isolate_id', $isolate->id)->with('test')->get() as $test) {
            $incubated = $test->read_at !== null && ! $test->read_at->equalTo($test->set_up_at);
            if ($incubated || $test->read_at === null) {
                $steps->push($this->step($test->set_up_at, $test->test->kind->label().' sembrada', $test->test->name, $test->set_up_by));
            }
            if ($test->read_at !== null) {
                $steps->push($this->step($test->read_at, $test->test->kind->label(), $test->test->name.': '.$test->result_label.($test->notes ? ' ('.$test->notes.')' : ''), $test->read_by));
            }
        }

        foreach (InstrumentRun::query()->where('isolate_id', $isolate->id)->get() as $run) {
            $cards = collect([
                $run->id_card_type ? 'ID '.trim($run->id_card_type.' '.($run->id_card_number ?? '')) : null,
                $run->ast_card_type ? 'ATB '.trim($run->ast_card_type.' '.($run->ast_card_number ?? '')) : null,
                $run->cassette ? 'casete '.$run->cassette.($run->position ? ' / '.$run->position : '') : null,
                $run->suspension ? 'suspensión '.$run->suspension : null,
            ])->filter()->implode(' · ');
            $steps->push($this->step($run->loaded_at, 'Carga en '.$run->instrument, $cards, $run->loaded_by));
        }

        foreach (Identification::query()->where('isolate_id', $isolate->id)->with(['organism', 'instrumentRun'])->get() as $id) {
            $detail = ($id->organism->name ?? 'Sin organismo').' · '.$id->method->label()
                .($id->confidence ? ' · '.$id->confidence : '')
                .($id->tests ? ' — '.$id->tests : '')
                .($id->notes ? ' — '.$id->notes : '');
            $steps->push($this->step($id->identified_at, 'Identificación '.mb_strtolower($id->level->label()), $detail, $id->identified_by));
        }

        $tests = SusceptibilityTest::query()->where('isolate_id', $isolate->id)
            ->with(['panel', 'breakpointSet', 'results.antibiotic.reportedAs'])->orderBy('id')->get();
        $susceptibility = [];
        foreach ($tests as $test) {
            $steps->push($this->step($test->set_up_at, 'Antibiograma preparado', $test->panel->name.' · '.$test->method->label(), $test->set_up_by));
            if ($test->cancelled_at !== null) {
                $steps->push($this->step($test->cancelled_at, 'Antibiograma descartado', (string) $test->cancellation_reason, $test->cancelled_by));
            }
            if ($test->read_at !== null) {
                $steps->push($this->step($test->read_at, 'Antibiograma leído', $test->breakpointSet ? 'Puntos de corte: '.$test->breakpointSet->name.' ('.$test->breakpointSet->edition.')' : '', $test->read_by));
            }
            $susceptibility[] = [
                'panel' => $test->panel->name,
                'is_vitek' => $test->panel->is_vitek,
                'status' => $test->status->label(),
                'breakpoints' => $test->breakpointSet ? $test->breakpointSet->name.' ('.$test->breakpointSet->edition.')' : null,
                'results' => $test->results->map(fn (SusceptibilityResult $r) => [
                    'antibiotic' => $r->antibiotic->displayName().($r->antibiotic->reportedAs ? ' → '.$r->antibiotic->reportedAs->name : ''),
                    'method' => $r->method->label(),
                    'is_additional' => $r->is_additional,
                    'value' => $r->measured_value === null ? null : trim(($r->mic_operator && $r->mic_operator !== '=' ? $r->mic_operator.' ' : '').rtrim(rtrim($r->measured_value, '0'), '.').' '.$r->method->unit()),
                    'automatic' => $r->automatic_interpretation?->value,
                    'final' => $r->finalInterpretation()?->value,
                    'override_reason' => $r->override_reason,
                    'rule_notes' => $r->rule_notes,
                    'is_reported' => $r->is_reported,
                    'read_at' => $r->read_at?->toIso8601String(),
                ])->values()->all(),
            ];
        }

        foreach (Referral::query()->where('isolate_id', $isolate->id)->get() as $referral) {
            $steps->push($this->step($referral->sent_at, 'Derivación', $referral->destination.' — '.$referral->reason, $referral->sent_by));
            if ($referral->result_at !== null) {
                $steps->push($this->step($referral->result_at, 'Resultado de la derivación', (string) $referral->result, $referral->result_recorded_by));
            }
        }

        return [
            'number' => $isolate->number,
            'morphotype' => $isolate->morphotype,
            'plate' => $isolate->plate?->medium->name,
            'count' => $isolate->count_category ? ($counts[$isolate->count_category]['label'] ?? $isolate->count_category) : null,
            'significance' => $isolate->significance->label(),
            'significance_reason' => $isolate->significance_reason,
            'status' => $isolate->status->label(),
            'organism' => $isolate->organism?->name,
            'no_ast_reason' => $isolate->no_ast_reason,
            'steps' => $steps->sortBy('sort')->values()->map(fn (array $s) => array_diff_key($s, ['sort' => true]))->all(),
            'susceptibility' => $susceptibility,
        ];
    }

    private function plateLabel(Plate $plate): string
    {
        return $plate->medium->name.' ('.$plate->inoculum.', '.$plate->incubation_temperature.', '.$plate->atmosphere->label().')';
    }

    /**
     * @return array{at: string|null, sort: string, title: string, detail: string, by: string|null}
     */
    private function step(CarbonInterface|string|null $at, string $title, string $detail, ?int $userId, ?string $by = null): array
    {
        $iso = $at instanceof CarbonInterface ? $at->toIso8601String() : $at;

        return [
            'at' => $iso,
            'sort' => (string) $iso,
            'title' => $title,
            'detail' => $detail,
            'by' => $by ?? ($userId === null ? null : $this->userName($userId)),
        ];
    }

    private function userName(int $id): string
    {
        return $this->users[$id] ??= (string) User::query()->whereKey($id)->value('name');
    }
}
