<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Data\PlateObservationData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\PlateObservation;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\PlateReading;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Services\ReadingSchedule;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function create(Request $request, Culture $culture, SampleHeader $header, ReadingSchedule $schedule): Response
    {
        $this->authorizeSite($request, $culture);
        $culture->load(['sampleStudy.protocolVersion', 'plates.medium', 'readings.plateReadings', 'isolates']);
        $stepHours = $schedule->stepHours($culture);
        $observations = array_map(fn (PlateObservation $o) => ['value' => $o->value, 'label' => $o->label()], PlateObservation::cases());
        $version = $culture->sampleStudy->protocolVersion;
        $options = collect($version->reading_options ?? [])->keyBy('code');
        $inWorkup = $culture->status === CultureStatus::Workup;
        // Con aislamientos cargados, en las lecturas siguientes solo cabe desarrollo nuevo o sin desarrollo nuevo.
        $allowed = $inWorkup ? ['growth', 'no_growth', 'reincubate'] : null;

        return Inertia::render('cultures/Reading', [
            'sample' => $header->for($culture->sampleStudy),
            'culture' => [
                'ulid' => $culture->ulid,
                'status' => $culture->status->value,
                'status_label' => $culture->status->label(),
                'inoculated_at' => $culture->inoculated_at->toIso8601String(),
                'available_at' => $schedule->nextReadingAvailableAt($culture)->toIso8601String(),
                'attempt' => $culture->readings->count() + 1,
                'step' => $schedule->step($culture) + 1,
                'steps' => $schedule->steps($culture),
                'step_hours' => $schedule->stepHours($culture),
                'is_last' => $schedule->isLastStep($culture),
                'in_workup' => $inWorkup,
                'pending_enrichments' => $schedule->pendingEnrichments($culture)->map(fn (Plate $p) => $p->medium->name)->values(),
                'enrichment_url' => $culture->plates->contains(fn (Plate $p) => $p->is_enrichment) ? route('enrichments.show', $culture) : null,
                'plates' => $culture->plates->reject(fn (Plate $p) => $p->is_enrichment || $p->source_isolate_id !== null)->map(fn (Plate $p) => [
                    'id' => $p->id,
                    'medium' => $p->medium->name,
                    'code' => $p->medium->code,
                    'inoculum' => $p->inoculum,
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'lost_reason' => $p->lost_reason,
                    'is_resown' => $p->replaces_plate_id !== null,
                    'due' => $p->status === PlateStatus::Incubating && $p->isReadAt($stepHours),
                    'hours' => $p->read_from_hours === null && $p->read_until_hours === null ? null
                        : trim(($p->read_from_hours !== null ? "desde las {$p->read_from_hours} h " : '').($p->read_until_hours !== null ? "hasta las {$p->read_until_hours} h" : '')),
                ])->values(),
                'isolates' => $culture->isolates->map(fn (Isolate $i) => [
                    'ulid' => $i->ulid,
                    'number' => $i->number,
                    'morphotype' => $i->morphotype,
                    'is_significant' => $i->is_significant,
                    'significance' => $i->significance->value,
                    'significance_label' => $i->significance->label(),
                ])->values(),
                'readings' => $culture->readings->map(fn (Reading $r) => [
                    'attempt' => $r->attempt,
                    'label' => $r->label ?? $options[$r->option_code]['label'] ?? $r->option_code,
                    'notes' => $r->notes,
                    'read_at' => $r->read_at->toIso8601String(),
                    'plates' => $r->plateReadings->map(fn (PlateReading $pr) => [
                        'medium' => $culture->plates->firstWhere('id', $pr->plate_id)?->medium->name,
                        'observation' => $pr->observation->label(),
                        'notes' => $pr->notes,
                    ])->values(),
                ])->values(),
                'study_id' => $culture->sample_study_id,
            ],
            'options' => collect($version->reading_options ?? [])
                ->filter(fn (array $o) => $allowed === null || in_array($o['outcome'], $allowed, true))
                ->values(),
            'countCategories' => $version->count_categories ?? [],
            'observations' => $observations,
            'significances' => array_map(fn (IsolateSignificance $s) => ['value' => $s->value, 'label' => $s->label()], IsolateSignificance::cases()),
            'can' => [
                'negative' => $request->user()?->can(Permission::CulturesReadNegative->value) ?? false,
                'positive' => $request->user()?->can(Permission::CulturesReadPositive->value) ?? false,
            ],
        ]);
    }

    public function store(Request $request, Culture $culture, RecordReading $record): RedirectResponse
    {
        $this->authorizeSite($request, $culture);

        $validated = $request->validate([
            'option_code' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'isolates' => ['array', 'max:10'],
            'isolates.*.morphotype' => ['required', 'string', 'max:255'],
            'isolates.*.count_category' => ['nullable', 'string', 'max:20'],
            'isolates.*.plate_id' => ['nullable', 'integer'],
            'isolates.*.is_significant' => ['sometimes', 'boolean'],
            'isolates.*.significance' => ['nullable', Rule::enum(IsolateSignificance::class)],
            'plates' => ['array', 'max:20'],
            'plates.*.plate_id' => ['required', 'integer'],
            'plates.*.observation' => ['required', Rule::enum(PlateObservation::class)],
            'plates.*.notes' => ['nullable', 'string', 'max:500'],
            'count_category' => ['nullable', 'string', 'max:20'],
        ], [], [
            'option_code' => 'opción de lectura',
            'isolates.*.morphotype' => 'morfotipo',
            'isolates.*.count_category' => 'recuento',
        ]);

        /** @var User $user */
        $user = $request->user();
        $record->handle(
            $user,
            $culture,
            $validated['option_code'],
            $validated['notes'] ?? null,
            array_map(fn (array $i) => new IsolateData(
                morphotype: $i['morphotype'],
                countCategory: $i['count_category'] ?? null,
                plateId: isset($i['plate_id']) ? (int) $i['plate_id'] : null,
                isSignificant: (bool) ($i['is_significant'] ?? true),
                significance: isset($i['significance']) ? IsolateSignificance::from($i['significance']) : null,
            ), array_values($validated['isolates'] ?? [])),
            EarlyConfirmation::fromRequest($request),
            array_map(fn (array $p) => new PlateObservationData(
                plateId: (int) $p['plate_id'],
                observation: PlateObservation::from($p['observation']),
                notes: $p['notes'] ?? null,
            ), array_values($validated['plates'] ?? [])),
            $validated['count_category'] ?? null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lectura registrada.']);

        return to_route('workbenches.show', 'reading');
    }

    private function authorizeSite(Request $request, Culture $culture): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can(Permission::CulturesReadNegative->value) || $user?->can(Permission::CulturesReadPositive->value),
            403,
        );
        abort_unless($culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
    }
}
