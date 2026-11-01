<?php

use App\Models\User;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Configuration\Database\Seeders\PlateCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\CriticalResults\Enums\CriticalAlertSource;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordDirectExam;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Actions\SubcultureEnrichmentBroth;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Actions\IssuePreliminaryReport;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PlateCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    $this->assistant = userWithRole($this->site, Role::Assistant);
    setPermissionsTeamId($this->site->id);
    $this->biochemist->unsetRelation('roles')->unsetRelation('permissions');
});

/**
 * Recibe una muestra de un estudio en placa por HTTP, como la pantalla de recepción.
 */
function receivePlateStudy(User $user, string $study, string $sampleType, string $barcode, string $priority = 'routine'): SampleStudy
{
    test()->actingAs($user)->post(route('reception.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'document_number' => fake()->unique()->numerify('########'),
        'last_name' => 'Sintético',
        'first_name' => 'Paciente',
        'sex' => 'U',
        'priority' => $priority,
        'samples' => [[
            'barcode' => $barcode,
            'sample_type_id' => SampleType::where('code', $sampleType)->value('id'),
            'study_ids' => [Study::where('code', $study)->value('id')],
        ]],
    ])->assertSessionHasNoErrors();
    setPermissionsTeamId(test()->site->id);

    return SampleStudy::query()->whereHas('sample', fn ($q) => $q->where('barcode', $barcode))->firstOrFail();
}

function plateActivity(ActivityType $type): ?Activity
{
    return Activity::where('type', $type->value)->whereNotIn('status', ['completed', 'cancelled'])->latest('id')->first();
}

function readCulture(User $user, Culture $culture, string $option, array $isolates = []): void
{
    app(RecordReading::class)->handle($user, $culture->fresh() ?? $culture, $option, null, $isolates);
}

/**
 * Aislamiento resuelto (identificación y antibiograma ya probados en el urocultivo),
 * dentro de una transacción como en la operación real.
 */
function resolveIsolate(Isolate $isolate): void
{
    DB::transaction(fn () => app(CultureClosure::class)->resolveIsolate($isolate));
}

test('wound culture: the reading schedule waits for 48 h before closing a negative', function () {
    $study = receivePlateStudy($this->biochemist, 'SEC', 'HER', 'S-1');
    $culture = app(InoculateSampleStudy::class)->handle($this->biochemist, $study);

    $this->travel(18)->hours();
    readCulture($this->biochemist, $culture, 'NEG');

    // Sin desarrollo a las 24 h no cierra: queda programada la lectura de las 48 h.
    $next = plateActivity(ActivityType::ReadCulture);
    expect($culture->fresh()->status)->toBe(CultureStatus::Incubating)
        ->and($next->attempt)->toBe(2)
        ->and($next->status)->toBe(ActivityStatus::Scheduled)
        ->and($next->available_at->equalTo($culture->inoculated_at->copy()->addHours(42)))->toBeTrue();

    expect(fn () => readCulture($this->biochemist, $culture, 'NEG'))->toThrow(ValidationException::class);

    $this->travel(24)->hours();
    readCulture($this->biochemist, $culture, 'NEG');

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedNegative)
        ->and($study->fresh()->status)->toBe(SampleStudyStatus::ReadyForValidation)
        ->and(plateActivity(ActivityType::ValidateReport))->not->toBeNull();

    $report = app(ValidateReport::class)->handle($this->biochemist, $study->fresh());
    expect($report->snapshot['result'])->toBe('Sin desarrollo');
});

test('growth at an intermediate reading keeps the scheduled readings and closes once isolates are resolved', function () {
    $study = receivePlateStudy($this->biochemist, 'SEC', 'ABS', 'S-2');
    $culture = app(InoculateSampleStudy::class)->handle($this->biochemist, $study);
    $plate = $culture->plates()->first();

    $this->travel(18)->hours();
    readCulture($this->biochemist, $culture, 'POS', [new IsolateData('Colonias beta hemolíticas', 'MOD', $plate->id, true)]);

    expect($culture->fresh()->status)->toBe(CultureStatus::Workup)
        ->and(plateActivity(ActivityType::ReadCulture)?->attempt)->toBe(2)
        ->and(plateActivity(ActivityType::Identify))->not->toBeNull();

    // Aislamiento resuelto antes de la última lectura: el cultivo sigue abierto.
    resolveIsolate(Isolate::firstOrFail());
    expect($culture->fresh()->status)->toBe(CultureStatus::Workup);

    // En la lectura siguiente solo cabe desarrollo nuevo o sin desarrollo nuevo.
    $this->travel(24)->hours();
    expect(fn () => readCulture($this->biochemist, $culture, 'NS'))->toThrow(ValidationException::class);
    readCulture($this->biochemist, $culture, 'POS', [new IsolateData('Colonias lactosa positivas', 'ESC', $plate->id, true)]);

    expect(Isolate::orderBy('number')->pluck('number')->all())->toBe([1, 2])
        ->and($culture->fresh()->status)->toBe(CultureStatus::Workup);

    // Lecturas completas: cierra cuando se resuelve el último aislamiento.
    resolveIsolate(Isolate::where('number', 2)->firstOrFail());
    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedPositive);

    $report = app(ValidateReport::class)->handle($this->biochemist, $study->fresh());
    expect($report->snapshot['result'])->toBe('Desarrollo')
        ->and($report->snapshot['isolates'])->toHaveCount(2);
});

test('the last scheduled reading without new growth closes a culture whose isolates are resolved', function () {
    $study = receivePlateStudy($this->biochemist, 'SEC', 'HER', 'S-3');
    $culture = app(InoculateSampleStudy::class)->handle($this->biochemist, $study);

    $this->travel(18)->hours();
    readCulture($this->biochemist, $culture, 'POS', [new IsolateData('Colonias blancas', null, null, true)]);
    resolveIsolate(Isolate::firstOrFail());

    $this->travel(24)->hours();
    readCulture($this->biochemist, $culture, 'NEG');

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedPositive)
        ->and($study->fresh()->status)->toBe(SampleStudyStatus::ReadyForValidation);
});

test('CSF: a direct exam with organisms opens an urgent critical result', function () {
    $study = receivePlateStudy($this->biochemist, 'LCR', 'LCR', 'L-1');

    // El directo de LCR va con prioridad inmediata (plantilla).
    $activity = plateActivity(ActivityType::DirectExam);
    expect($activity->priority)->toBe(Priority::Stat);

    // Validación de opciones: solo las del protocolo y una por campo simple.
    expect(fn () => app(RecordDirectExam::class)->handle($this->biochemist, $study, ['MO' => ['XXX']]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RecordDirectExam::class)->handle($this->biochemist, $study, ['LEU' => ['ESC', 'ABU']]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RecordDirectExam::class)->handle($this->assistant, $study, ['MO' => ['DGN']]))
        ->toThrow(AuthorizationException::class);

    $exam = app(RecordDirectExam::class)->handle($this->biochemist, $study, ['LEU' => ['ABU'], 'MO' => ['DGN']], 'Abundantes PMN');
    // Repetir lo mismo es idempotente; cambiarlo no se permite (corrección: etapa 4e).
    expect(app(RecordDirectExam::class)->handle($this->biochemist, $study, ['LEU' => ['ABU'], 'MO' => ['DGN']], 'Abundantes PMN')->id)->toBe($exam->id)
        ->and(fn () => app(RecordDirectExam::class)->handle($this->biochemist, $study, ['MO' => ['NO']]))
        ->toThrow(ValidationException::class);

    $alert = CriticalAlert::firstOrFail();
    expect($exam->organisms_seen)->toBeTrue()
        ->and($alert->source)->toBe(CriticalAlertSource::DirectExam)
        ->and($alert->summary)->toContain('L-1')->toContain('Diplococos gram negativos')
        ->and(plateActivity(ActivityType::DirectExam))->toBeNull()
        ->and(plateActivity(ActivityType::CommunicateCritical)?->priority)->toBe(Priority::Stat);

    // La barra superior lleva a la pantalla del directo.
    $this->actingAs($this->biochemist)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('criticalAlerts.0.url', route('direct-exams.show', $study->id)));

    $this->actingAs($this->biochemist)->post(route('critical-alerts.communications.store', $alert), [
        'idempotency_key' => (string) Str::uuid(), 'recipient_name' => 'Dr. Sintético', 'recipient_role' => 'Guardia',
        'channel' => 'phone', 'successful' => true, 'read_back_confirmed' => true,
    ])->assertSessionHasNoErrors();
    expect($alert->refresh()->status)->toBe(CriticalAlertStatus::Communicated)
        ->and(plateActivity(ActivityType::CommunicateCritical))->toBeNull();

    // Preliminar con el directo: después de la siembra, sin adelantar el cultivo.
    expect(fn () => app(IssuePreliminaryReport::class)->handle($this->biochemist, $study->fresh()))->toThrow(ValidationException::class);
    app(InoculateSampleStudy::class)->handle($this->biochemist, $study->fresh());
    $preliminary = app(IssuePreliminaryReport::class)->handle($this->biochemist, $study->fresh());

    expect($preliminary->snapshot['result'])->toBe('Cultivo en curso')
        ->and($preliminary->snapshot['direct_exam']['lines'])->toBe([
            ['field' => 'Leucocitos', 'values' => ['Abundantes']],
            ['field' => 'Microorganismos', 'values' => ['Diplococos gram negativos']],
        ])
        ->and($study->fresh()->status)->toBe(SampleStudyStatus::PreliminaryReported);

    expect(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('a direct exam without organisms, or outside CSF, does not open a critical result', function () {
    $csf = receivePlateStudy($this->biochemist, 'LCR', 'LCR', 'L-2');
    app(RecordDirectExam::class)->handle($this->biochemist, $csf, ['MO' => ['NO']]);

    $pleural = receivePlateStudy($this->biochemist, 'LIQ', 'LPL', 'P-1');
    app(RecordDirectExam::class)->handle($this->biochemist, $pleural, ['MO' => ['BGN']]);

    expect(CriticalAlert::count())->toBe(0)
        ->and(plateActivity(ActivityType::CommunicateCritical))->toBeNull();
});

test('stool culture: the selenite broth is subcultured before the last reading', function () {
    $study = receivePlateStudy($this->biochemist, 'COPRO', 'MF', 'C-1');
    $culture = app(InoculateSampleStudy::class)->handle($this->biochemist, $study);
    $broth = Plate::where('is_enrichment', true)->firstOrFail();

    expect($culture->plates()->count())->toBe(5)
        ->and(plateActivity(ActivityType::SubcultureEnrichment)?->status)->toBe(ActivityStatus::Scheduled)
        ->and(fn () => app(SubcultureEnrichmentBroth::class)->handle($this->biochemist, $broth))
        ->toThrow(ValidationException::class);

    // Lectura de las 24 h con el caldo sin repicar: sigue (no es la última).
    $this->travel(18)->hours();
    readCulture($this->biochemist, $culture, 'NEG');

    // La última no se registra con el caldo pendiente.
    $this->travel(24)->hours();
    expect(fn () => readCulture($this->biochemist, $culture, 'NEG'))->toThrow(ValidationException::class);

    $plates = app(SubcultureEnrichmentBroth::class)->handle($this->assistant, $broth);
    expect($plates->map(fn (Plate $p) => $p->medium->code)->all())->toBe(['SS', 'XLD'])
        ->and($broth->fresh()->status)->toBe(PlateStatus::Finished)
        ->and(plateActivity(ActivityType::SubcultureEnrichment))->toBeNull()
        // Idempotente.
        ->and(app(SubcultureEnrichmentBroth::class)->handle($this->assistant, $broth)->count())->toBe(2);

    // Las placas del repique necesitan su incubación mínima antes de la última lectura.
    expect(fn () => readCulture($this->biochemist, $culture, 'NEG'))->toThrow(ValidationException::class);
    $this->travel(18)->hours();
    readCulture($this->biochemist, $culture, 'NEG');

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedNegative);
    $report = app(ValidateReport::class)->handle($this->biochemist, $study->fresh());
    expect($report->snapshot['result'])->toBe('No se aislaron Salmonella, Shigella ni Campylobacter');
});

test('every reference template can be received and inoculated', function () {
    $pairs = [
        ['SEC', 'HER'], ['SEC', 'ABS'], ['LIQ', 'LPL'], ['LIQ', 'LAS'], ['LIQ', 'LAR'], ['LCR', 'LCR'],
        ['RESP', 'ESP'], ['RESP', 'ATQ'], ['RESP', 'BAL'], ['COPRO', 'MF'],
        ['GEN', 'FV'], ['GEN', 'URE'], ['GEN', 'ENC'], ['FAU', 'HFA'],
    ];

    foreach ($pairs as $index => [$study, $type]) {
        $sampleStudy = receivePlateStudy($this->biochemist, $study, $type, "T-{$index}");
        expect($sampleStudy->protocolVersion->is_reference_template)->toBeTrue();
        app(InoculateSampleStudy::class)->handle($this->biochemist, $sampleStudy);
    }

    expect(Culture::count())->toBe(count($pairs))
        // El exudado de fauces no lleva directo; el resto sí.
        ->and(Activity::where('type', ActivityType::DirectExam->value)->count())->toBe(count($pairs) - 1);
});

test('processing workbench, direct exam and broth pages over HTTP', function () {
    $csf = receivePlateStudy($this->biochemist, 'LCR', 'LCR', 'L-3');
    $stool = receivePlateStudy($this->biochemist, 'COPRO', 'MF', 'C-2');
    $culture = app(InoculateSampleStudy::class)->handle($this->biochemist, $stool);

    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'processing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflow/Workbench')
            ->where('items.0.type', ActivityType::DirectExam->value)
            ->where('items.0.priority', 'stat')
            ->where('items.0.url', route('direct-exams.show', $csf->id)));

    $this->actingAs($this->biochemist)->get(route('direct-exams.show', $csf->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cultures/DirectExam')
            ->where('settings.critical_if_organisms', true)
            ->where('exam', null)
            ->where('can.perform', true));

    $this->actingAs($this->biochemist)->post(route('direct-exams.store', $csf->id), [
        'results' => ['MO' => ['NO'], 'LEU' => ['ESC']],
    ])->assertRedirect(route('direct-exams.show', $csf->id))->assertSessionHasNoErrors();

    $this->actingAs($this->biochemist)->get(route('direct-exams.show', $csf->id))
        ->assertInertia(fn (Assert $page) => $page->where('exam.lines.1.values.0', 'No se observan microorganismos'));

    // El Auxiliar no registra directos.
    $this->actingAs($this->assistant)->post(route('direct-exams.store', $stool->id), ['results' => ['LEU' => ['NO']]])
        ->assertForbidden();

    // Repique del caldo desde su pantalla.
    $this->travel(18)->hours();
    $this->actingAs($this->assistant)->get(route('enrichments.show', $culture))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cultures/Enrichment')
            ->where('broths.0.available', true)
            ->where('broths.0.targets', ['SS', 'XLD']));

    $broth = Plate::where('is_enrichment', true)->firstOrFail();
    $this->actingAs($this->assistant)->post(route('enrichments.store', $broth))
        ->assertRedirect(route('workbenches.show', 'processing'));
    expect($broth->fresh()->status)->toBe(PlateStatus::Finished);

    // La lectura muestra el cronograma.
    $this->actingAs($this->biochemist)->get(route('readings.create', $culture))
        ->assertInertia(fn (Assert $page) => $page
            ->where('culture.steps', 2)
            ->where('culture.step', 1)
            ->where('culture.step_hours', 18)
            ->where('culture.plates', fn ($plates) => collect($plates)->doesntContain('code', 'SEL')));
});
