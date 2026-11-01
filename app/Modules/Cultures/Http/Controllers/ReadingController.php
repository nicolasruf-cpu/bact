<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Cultures\Services\ReadingSchedule;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function create(Request $request, Culture $culture, SampleHeader $header, ReadingSchedule $schedule): Response
    {
        $this->authorizeSite($request, $culture);
        $culture->load(['sampleStudy.protocolVersion', 'plates.medium', 'readings']);
        $version = $culture->sampleStudy->protocolVersion;
        $options = collect($version->reading_options ?? [])->keyBy('code');

        return Inertia::render('cultures/Reading', [
            'sample' => $header->for($culture->sampleStudy),
            'culture' => [
                'ulid' => $culture->ulid,
                'status' => $culture->status->value,
                'status_label' => $culture->status->label(),
                'inoculated_at' => $culture->inoculated_at->toIso8601String(),
                'available_at' => $schedule->nextReadingAvailableAt($culture)->toIso8601String(),
                'attempt' => $culture->readings->count() + 1,
                'plates' => $culture->plates->map(fn (Plate $p) => [
                    'id' => $p->id,
                    'medium' => $p->medium->name,
                    'code' => $p->medium->code,
                    'inoculum' => $p->inoculum,
                ])->values(),
                'readings' => $culture->readings->map(fn (Reading $r) => [
                    'attempt' => $r->attempt,
                    'label' => $options[$r->option_code]['label'] ?? $r->option_code,
                    'notes' => $r->notes,
                    'read_at' => $r->read_at->toIso8601String(),
                ])->values(),
            ],
            'options' => $version->reading_options ?? [],
            'countCategories' => $version->count_categories ?? [],
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
            'isolates.*.is_significant' => ['required', 'boolean'],
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
                isSignificant: (bool) $i['is_significant'],
            ), array_values($validated['isolates'] ?? [])),
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
