<?php

namespace App\Modules\BloodCultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BloodCultures\Actions\ConfirmNegativeBottle;
use App\Modules\BloodCultures\Actions\LoadBottle;
use App\Modules\BloodCultures\Actions\PerformBlindSubculture;
use App\Modules\BloodCultures\Actions\ReadBlindSubculture;
use App\Modules\BloodCultures\Actions\RecordBottlePositive;
use App\Modules\BloodCultures\Actions\RecordGramStain;
use App\Modules\BloodCultures\Actions\SubcultureBottle;
use App\Modules\BloodCultures\Enums\BlindSubcultureResult;
use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\BloodCultures\Queries\BottlePageQuery;
use App\Modules\BloodCultures\Queries\OrderBloodCultureSummary;
use App\Modules\CriticalResults\Enums\CommunicationChannel;
use App\Modules\CriticalResults\Queries\StudyCriticalAlertsQuery;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Queries\StudyReportsQuery;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla del frasco y sus operaciones. La lógica está en las acciones; acá solo se valida
 * la forma de los datos y que el frasco sea de la sede activa.
 */
class BloodCultureController extends Controller
{
    /** Con cualquiera de estos permisos se ve la pantalla del frasco. */
    private const VIEW_PERMISSIONS = [
        Permission::BloodCulturesLoad,
        Permission::BloodCulturesSignalPositive,
        Permission::BloodCulturesConfirmNegative,
        Permission::GramPerform,
        Permission::CulturesReadNegative,
        Permission::CulturesReadPositive,
    ];

    public function show(
        Request $request,
        SampleStudy $sampleStudy,
        SampleHeader $header,
        BottlePageQuery $page,
        OrderBloodCultureSummary $summary,
        StudyCriticalAlertsQuery $alerts,
        StudyReportsQuery $reports,
    ): Response {
        $this->authorizeView($request, $sampleStudy);
        abort_unless($sampleStudy->protocolVersion->isBloodCulture(), 404);

        $user = $request->user();
        $can = fn (Permission $p): bool => (bool) $user?->can($p->value);

        return Inertia::render('blood-cultures/Bottle', [
            'sample' => $header->for($sampleStudy),
            'studyStatus' => $sampleStudy->status->value,
            ...$page->for($sampleStudy),
            'orderSummary' => $summary->forOrder($sampleStudy->sample->lab_order_id),
            'criticalAlerts' => $alerts->forStudy($sampleStudy->id),
            'reports' => $reports->forStudy($sampleStudy->id),
            'channels' => collect(CommunicationChannel::cases())
                ->map(fn (CommunicationChannel $c) => ['value' => $c->value, 'label' => $c->label()])->all(),
            'can' => [
                'load' => $can(Permission::BloodCulturesLoad),
                'signal_positive' => $can(Permission::BloodCulturesSignalPositive),
                'confirm_negative' => $can(Permission::BloodCulturesConfirmNegative),
                'subculture' => $can(Permission::CulturesInoculate),
                'read_negative' => $can(Permission::CulturesReadNegative),
                'read_positive' => $can(Permission::CulturesReadPositive),
                'gram' => $can(Permission::GramPerform),
                'communicate' => $can(Permission::CriticalCommunicate),
                'preliminary' => $can(Permission::ReportsValidate),
            ],
        ]);
    }

    public function load(Request $request, SampleStudy $sampleStudy, LoadBottle $load): RedirectResponse
    {
        $this->authorizeView($request, $sampleStudy);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(BottleMethod::class)],
            'bottle_type' => ['required', 'string', 'max:20'],
            'instrument' => ['nullable', 'string', 'max:50'],
            'instrument_position' => ['nullable', 'string', 'max:30'],
            'loaded_at' => ['nullable', 'date'],
        ], [], [
            'method' => 'procesamiento',
            'bottle_type' => 'tipo de frasco',
            'instrument' => 'equipo',
            'instrument_position' => 'posición',
            'loaded_at' => 'hora de carga',
        ]);

        $load->handle(
            $this->user($request),
            $sampleStudy,
            BottleMethod::from($validated['method']),
            $validated['bottle_type'],
            $validated['instrument'] ?? null,
            $validated['instrument_position'] ?? null,
            isset($validated['loaded_at']) ? Date::parse($validated['loaded_at']) : null,
        );

        return $this->back($sampleStudy, 'Frasco cargado.');
    }

    public function positive(Request $request, BloodCultureBottle $bottle, RecordBottlePositive $record): RedirectResponse
    {
        $this->authorizeView($request, $bottle->sampleStudy);

        $validated = $request->validate([
            'positive_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['positive_at' => 'hora de positividad']);

        $record->handle(
            $this->user($request),
            $bottle,
            isset($validated['positive_at']) ? Date::parse($validated['positive_at']) : null,
            $validated['notes'] ?? null,
        );

        return $this->back($bottle->sampleStudy, 'Frasco positivo registrado: Gram y subcultivo con prioridad inmediata.');
    }

    public function negative(Request $request, BloodCultureBottle $bottle, ConfirmNegativeBottle $confirm): RedirectResponse
    {
        $this->authorizeView($request, $bottle->sampleStudy);
        $confirm->handle($this->user($request), $bottle);

        return $this->back($bottle->sampleStudy, 'Frasco cerrado como negativo: pasa a validación.');
    }

    public function blindSubculture(Request $request, BloodCultureBottle $bottle, PerformBlindSubculture $perform): RedirectResponse
    {
        $this->authorizeView($request, $bottle->sampleStudy);
        $perform->handle($this->user($request), $bottle);

        return $this->back($bottle->sampleStudy, 'Repique registrado.');
    }

    public function readBlindSubculture(Request $request, BlindSubculture $subculture, ReadBlindSubculture $read): RedirectResponse
    {
        $study = $subculture->bottle->sampleStudy;
        $this->authorizeView($request, $study);

        $validated = $request->validate([
            'result' => ['required', Rule::enum(BlindSubcultureResult::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['result' => 'resultado']);

        $read->handle($this->user($request), $subculture, BlindSubcultureResult::from($validated['result']), $validated['notes'] ?? null);

        return $this->back($study, 'Lectura del repique registrada.');
    }

    public function gram(Request $request, BloodCultureBottle $bottle, RecordGramStain $record): RedirectResponse
    {
        $this->authorizeView($request, $bottle->sampleStudy);

        $validated = $request->validate([
            'option_code' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], ['option_code' => 'resultado del Gram']);

        $record->handle($this->user($request), $bottle, $validated['option_code'], $validated['description'] ?? null);

        return $this->back($bottle->sampleStudy, 'Gram registrado.');
    }

    public function subculture(Request $request, BloodCultureBottle $bottle, SubcultureBottle $subculture): RedirectResponse
    {
        $this->authorizeView($request, $bottle->sampleStudy);
        $subculture->handle($this->user($request), $bottle);

        return $this->back($bottle->sampleStudy, 'Subcultivo registrado: la lectura queda programada.');
    }

    private function authorizeView(Request $request, SampleStudy $study): void
    {
        $user = $request->user();
        abort_unless(collect(self::VIEW_PERMISSIONS)->contains(fn (Permission $p) => (bool) $user?->can($p->value)), 403);
        abort_unless($study->processing_site_id === getPermissionsTeamId(), 403);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function back(SampleStudy $study, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('blood-cultures.show', $study);
    }
}
