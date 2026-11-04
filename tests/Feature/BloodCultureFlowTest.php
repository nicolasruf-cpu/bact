<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Enums\PositivitySource;
use App\Modules\BloodCultures\Models\BlindSubculture;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Configuration\Database\Seeders\BloodCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\CriticalResults\Enums\CriticalAlertStatus;
use App\Modules\CriticalResults\Models\CriticalAlert;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Enums\ReportStatus;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Models\Report;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(BloodCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->seed(SyntheticBreakpointSeeder::class);
    $this->site = Site::factory()->create();
    $this->assistant = userWithRole($this->site, Role::Assistant);
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
});

/**
 * Orden SINTÉTICA con hemocultivos y retrocultivos: cada frasco es una muestra con su código.
 *
 * @param  list<string>  $hemo
 * @param  list<string>  $retro
 */
function receiveBloodOrder(User $user, array $hemo, array $retro = []): void
{
    $sample = fn (string $barcode, string $type, string $study) => [
        'barcode' => $barcode,
        'sample_type_id' => SampleType::where('code', $type)->value('id'),
        'study_ids' => [Study::where('code', $study)->value('id')],
    ];

    test()->actingAs($user)->post(route('reception.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'document_number' => fake()->unique()->numerify('########'),
        'last_name' => 'Sintético',
        'first_name' => 'Paciente',
        'sex' => 'U',
        'priority' => 'routine',
        'samples' => [
            ...array_map(fn ($b) => $sample($b, 'SAN-PER', 'HEMO'), $hemo),
            ...array_map(fn ($b) => $sample($b, 'SAN-CAT', 'RETRO'), $retro),
        ],
    ])->assertSessionHasNoErrors();
}

function bloodStudy(string $barcode): SampleStudy
{
    return SampleStudy::query()->whereHas('sample', fn ($q) => $q->where('barcode', $barcode))->sole();
}

function loadBottleFor(User $user, string $barcode, string $method = 'automated'): BloodCultureBottle
{
    test()->actingAs($user)->post(route('blood-cultures.load', bloodStudy($barcode)), [
        'method' => $method,
        'bottle_type' => 'AER',
        'instrument' => $method === 'automated' ? 'BacT/ALERT' : null,
        'instrument_position' => $method === 'automated' ? 'A-12' : null,
    ])->assertSessionHasNoErrors();

    return BloodCultureBottle::query()->where('sample_study_id', bloodStudy($barcode)->id)->sole();
}

function openBloodActivity(ActivityType $type): ?Activity
{
    return Activity::query()->where('type', $type->value)
        ->whereNotIn('status', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
        ->first();
}

test('reception creates a bottle-load activity per blood culture, not an inoculation', function () {
    receiveBloodOrder($this->assistant, ['H-1', 'H-2'], ['R-1']);

    expect(Activity::where('type', ActivityType::LoadBottle->value)->count())->toBe(3)
        ->and(Activity::where('type', ActivityType::Inoculate->value)->count())->toBe(0);

    $this->actingAs($this->assistant)->get(route('workbenches.blood-cultures'))
        ->assertInertia(fn (Assert $page) => $page->component('blood-cultures/Workbench')->has('items', 3));

    // No se puede sembrar en placa un hemocultivo.
    $this->actingAs($this->assistant)->post(route('inoculations.store', bloodStudy('H-1')))->assertSessionHasErrors('study');
});

test('automated positive bottle end to end: gram, critical communication, preliminary and final report', function () {
    receiveBloodOrder($this->assistant, ['H-1', 'H-2'], ['R-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1');
    loadBottleFor($this->assistant, 'H-2');
    loadBottleFor($this->assistant, 'R-1');

    expect($bottle->incubation_ends_at->diffInDays($bottle->loaded_at, true))->toEqual(5)
        ->and(openBloodActivity(ActivityType::LoadBottle))->toBeNull()
        ->and(Activity::where('type', ActivityType::ConfirmNegativeBottle->value)->where('status', ActivityStatus::Scheduled->value)->count())->toBe(3);

    // El equipo da la alarma a las 14 h; lo registra el Auxiliar con la hora del equipo.
    $this->travel(15)->hours();
    $this->actingAs($this->assistant)->post(route('bottles.positive', $bottle), [
        'positive_at' => $bottle->loaded_at->copy()->addHours(14)->toIso8601String(),
    ])->assertSessionHasNoErrors();

    $bottle->refresh();
    expect($bottle->status)->toBe(BottleStatus::Positive)
        ->and($bottle->positivity_source)->toBe(PositivitySource::Instrument)
        ->and($bottle->minutesToPositivity())->toBe(14 * 60);

    // Se cancela la descarga del negativo de ese frasco; Gram y subcultivo con prioridad inmediata.
    expect(Activity::where('subject_id', $bottle->id)->where('type', ActivityType::ConfirmNegativeBottle->value)->value('status'))
        ->toBe(ActivityStatus::Cancelled)
        ->and(openBloodActivity(ActivityType::GramStain)?->priority->value)->toBe('stat')
        ->and(openBloodActivity(ActivityType::SubcultureBottle)?->priority->value)->toBe('stat');

    // El Auxiliar no hace el Gram.
    $this->actingAs($this->assistant)->post(route('bottles.gram', $bottle), ['option_code' => 'BGN'])->assertForbidden();

    $this->actingAs($this->biochemist)->post(route('bottles.gram', $bottle), [
        'option_code' => 'BGN',
        'description' => 'Bacilos gram negativos abundantes',
    ])->assertSessionHasNoErrors();

    // Resultado crítico abierto, con actividad de comunicación y barra superior.
    $alert = CriticalAlert::sole();
    expect($alert->status)->toBe(CriticalAlertStatus::Open)
        ->and($alert->summary)->toContain('Hemocultivo positivo')->toContain('Bacilos gram negativos')
        ->and(openBloodActivity(ActivityType::CommunicateCritical)?->priority->value)->toBe('stat');
    $this->actingAs($this->biochemist)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('criticalAlerts', 1));

    // Un intento fallido no lo cierra; uno exitoso sí (idempotente por clave).
    $this->actingAs($this->biochemist)->post(route('critical-alerts.communications.store', $alert), [
        'idempotency_key' => (string) Str::uuid(), 'recipient_name' => 'Dra. Ficticia', 'recipient_role' => 'Guardia',
        'channel' => 'phone', 'successful' => false,
    ])->assertSessionHasNoErrors();
    expect($alert->refresh()->status)->toBe(CriticalAlertStatus::Open);

    $key = (string) Str::uuid();
    foreach ([1, 2] as $attempt) {
        $this->actingAs($this->biochemist)->post(route('critical-alerts.communications.store', $alert), [
            'idempotency_key' => $key, 'recipient_name' => 'Dr. Sintético', 'recipient_role' => 'UTI',
            'channel' => 'phone', 'successful' => true, 'read_back_confirmed' => true,
        ])->assertSessionHasNoErrors();
    }
    expect($alert->refresh()->status)->toBe(CriticalAlertStatus::Communicated)
        ->and($alert->communications()->count())->toBe(2)
        ->and(openBloodActivity(ActivityType::CommunicateCritical))->toBeNull();

    // Informe preliminar con el Gram y el resumen de la orden.
    $study = bloodStudy('H-1');
    $this->actingAs($this->biochemist)->post(route('reports.preliminary', $study))->assertSessionHasNoErrors();
    $preliminary = Report::sole();
    expect($preliminary->type)->toBe(ReportType::Preliminary)
        ->and($preliminary->snapshot['blood_culture']['gram']['result'])->toBe('Bacilos gram negativos')
        ->and($preliminary->snapshot['blood_culture']['minutes_to_positivity'])->toBe(840)
        ->and($preliminary->snapshot['blood_culture']['order_summary'][0]['text'])->toBe('Hemocultivo: 1 de 2 positivos')
        ->and($preliminary->snapshot['blood_culture']['order_summary'][1]['text'])->toBe('Retrocultivo: 0 de 1 positivo')
        ->and($study->refresh()->status)->toBe(SampleStudyStatus::PreliminaryReported);

    // Subcultivo → circuito común: lectura, aislamiento, identificación, antibiograma.
    $this->actingAs($this->assistant)->post(route('bottles.subculture', $bottle))->assertRedirect()->assertSessionHasNoErrors();
    $culture = Culture::sole();
    expect($culture->plates()->count())->toBe(3)
        ->and(openBloodActivity(ActivityType::SubcultureBottle))->toBeNull();

    $this->travel(18)->hours();
    $this->actingAs($this->biochemist)->post(route('readings.store', $culture), [
        'option_code' => 'POS',
        'isolates' => [['morphotype' => 'BGN lactosa +', 'is_significant' => true]],
    ])->assertSessionHasNoErrors();
    $isolate = Isolate::sole();
    $this->actingAs($this->biochemist)->post(route('identifications.store', $isolate), [
        'organism_id' => Organism::where('code', 'ECOL')->value('id'), 'method' => 'biochemical', 'level' => 'definitive', 'requires_susceptibility' => true,
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->biochemist)->post(route('susceptibility.setup', $isolate), ['method' => 'disk'])->assertSessionHasNoErrors();
    $this->travel(16)->hours();
    $test = SusceptibilityTest::with('results')->sole();
    $this->actingAs($this->biochemist)->post(route('susceptibility.read', $isolate), [
        'results' => $test->results->map(fn ($r) => ['antibiotic_id' => $r->antibiotic_id, 'value' => 25, 'is_reported' => true])->all(),
    ])->assertSessionHasNoErrors();

    expect($study->refresh()->status)->toBe(SampleStudyStatus::ReadyForValidation);

    $this->actingAs($this->biochemist)->post(route('validations.store', $study), ['confirm' => true])->assertSessionHasNoErrors();
    $final = Report::where('type', ReportType::Final->value)->sole();
    expect($final->snapshot['result'])->toBe('Desarrollo')
        ->and($final->snapshot['isolates'][0]['organism'])->toBe('Escherichia coli')
        ->and($final->snapshot['blood_culture']['gram']['result'])->toBe('Bacilos gram negativos')
        ->and($preliminary->refresh()->status)->toBe(ReportStatus::Superseded);

    $this->actingAs($this->biochemist)->get(route('blood-cultures.show', $study))
        ->assertInertia(fn (Assert $page) => $page->component('blood-cultures/Bottle')
            ->where('bottle.status', 'positive')
            ->has('reports', 2)
            ->where('orderSummary.0.positive', 1)
            ->where('orderSummary.0.total', 2));
});

test('automated negative bottle closes only after the protocol incubation days', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1');

    // El Auxiliar no informa negativos; el Bioquímico, antes de los 5 días, solo confirmando (D-0020).
    $this->actingAs($this->assistant)->post(route('bottles.negative', $bottle))->assertForbidden();
    $this->travel(4)->days();
    $this->actingAs($this->biochemist)->post(route('bottles.negative', $bottle))->assertSessionHasErrors('early_confirmed');

    $this->travel(1)->days();
    $this->actingAs($this->biochemist)->get(route('workbenches.blood-cultures'))
        ->assertInertia(fn (Assert $page) => $page->where('items.0.type', 'confirm_negative_bottle'));
    $this->actingAs($this->biochemist)->post(route('bottles.negative', $bottle))->assertSessionHasNoErrors();

    $study = bloodStudy('H-1');
    expect($bottle->refresh()->status)->toBe(BottleStatus::Negative)
        ->and($study->refresh()->status)->toBe(SampleStudyStatus::ReadyForValidation)
        ->and(openBloodActivity(ActivityType::ValidateReport))->not->toBeNull();

    $this->actingAs($this->biochemist)->post(route('validations.store', $study), ['confirm' => true])->assertSessionHasNoErrors();
    expect(Report::sole()->snapshot['result'])->toBe('Sin desarrollo a los 5 días de incubación');
});

test('manual bottle: daily blind subcultures until the last one is read without growth', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1', 'manual');
    expect($bottle->incubationDays())->toBe(7);

    // El primer repique corresponde a las 24 h (antes, solo confirmando).
    $this->actingAs($this->assistant)->post(route('bottles.blind-subcultures.store', $bottle))->assertSessionHasErrors('early_confirmed');

    for ($day = 1; $day <= 7; $day++) {
        $this->travel(24)->hours();
        $this->actingAs($this->assistant)->post(route('bottles.blind-subcultures.store', $bottle))->assertSessionHasNoErrors();
        $subculture = BlindSubculture::where('number', $day)->sole();

        // El Auxiliar no lee; antes de la incubación mínima de la placa, solo confirmando.
        $this->actingAs($this->assistant)->post(route('blind-subcultures.read', $subculture), ['result' => 'no_growth'])->assertForbidden();
        $this->actingAs($this->biochemist)->post(route('blind-subcultures.read', $subculture), ['result' => 'no_growth'])->assertSessionHasErrors('early_confirmed');

        $this->travel(18)->hours();
        $this->actingAs($this->biochemist)->post(route('blind-subcultures.read', $subculture), ['result' => 'no_growth'])->assertSessionHasNoErrors();
        $this->travel(-18)->hours();

        expect($bottle->refresh()->status)->toBe($day < 7 ? BottleStatus::Incubating : BottleStatus::Negative);
    }

    expect(BlindSubculture::count())->toBe(7)
        ->and(openBloodActivity(ActivityType::BlindSubculture))->toBeNull()
        ->and(bloodStudy('H-1')->status)->toBe(SampleStudyStatus::ReadyForValidation);

    $this->actingAs($this->biochemist)->post(route('validations.store', bloodStudy('H-1')), ['confirm' => true])->assertSessionHasNoErrors();
    expect(Report::sole()->snapshot['result'])->toBe('Sin desarrollo a los 7 días de incubación');
});

test('manual bottle: growth in a blind subculture makes it positive and the plate becomes the culture', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1', 'manual');

    $this->travel(24)->hours();
    $this->actingAs($this->assistant)->post(route('bottles.blind-subcultures.store', $bottle));
    $this->travel(20)->hours();
    $this->actingAs($this->biochemist)->post(route('blind-subcultures.read', BlindSubculture::sole()), ['result' => 'growth'])->assertRedirect()->assertSessionHasNoErrors();

    $bottle->refresh();
    expect($bottle->status)->toBe(BottleStatus::Positive)
        ->and($bottle->positivity_source)->toBe(PositivitySource::BlindSubculture)
        ->and($bottle->minutesToPositivity())->toBeNull()
        // Gram sí; subcultivo no: ya hay placa con desarrollo, que pasa a lectura.
        ->and(openBloodActivity(ActivityType::GramStain))->not->toBeNull()
        ->and(openBloodActivity(ActivityType::SubcultureBottle))->toBeNull()
        ->and(openBloodActivity(ActivityType::BlindSubculture))->toBeNull();

    $culture = Culture::sole();
    expect($culture->plates()->sole()->medium->code)->toBe('CHOC');
    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'reading'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1));
});

test('manual bottle: visible signs make it positive with time to positivity', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1', 'manual');
    $this->travel(30)->hours();

    $this->actingAs($this->assistant)->post(route('bottles.positive', $bottle), ['notes' => 'Turbidez'])->assertSessionHasNoErrors();

    expect($bottle->refresh()->positivity_source)->toBe(PositivitySource::Visual)
        ->and(openBloodActivity(ActivityType::SubcultureBottle))->not->toBeNull()
        ->and(openBloodActivity(ActivityType::BlindSubculture))->toBeNull();
});

test('bottle times are validated against reception and the clock', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $study = bloodStudy('H-1');

    $this->actingAs($this->assistant)->post(route('blood-cultures.load', $study), [
        'method' => 'automated', 'bottle_type' => 'AER', 'instrument' => 'BacT/ALERT', 'loaded_at' => now()->addHour()->toIso8601String(),
    ])->assertSessionHasErrors('loaded_at');
    $this->actingAs($this->assistant)->post(route('blood-cultures.load', $study), [
        'method' => 'automated', 'bottle_type' => 'XXX', 'instrument' => 'BacT/ALERT',
    ])->assertSessionHasErrors('bottle_type');
    $this->actingAs($this->assistant)->post(route('blood-cultures.load', $study), [
        'method' => 'automated', 'bottle_type' => 'AER',
    ])->assertSessionHasErrors('instrument');

    $bottle = loadBottleFor($this->assistant, 'H-1');
    $this->actingAs($this->assistant)->post(route('bottles.positive', $bottle), [
        'positive_at' => $bottle->loaded_at->copy()->subHour()->toIso8601String(),
    ])->assertSessionHasErrors('positive_at');
});

test('blood culture screens of another site are not accessible', function () {
    receiveBloodOrder($this->assistant, ['H-1']);
    $bottle = loadBottleFor($this->assistant, 'H-1');
    $other = userWithRole(Site::factory()->create(), Role::Biochemist);

    $this->actingAs($other)->get(route('blood-cultures.show', bloodStudy('H-1')))->assertForbidden();
    $this->actingAs($other)->post(route('bottles.positive', $bottle))->assertForbidden();
    $this->actingAs(userWithRole($this->site, Role::Viewer))->get(route('workbenches.blood-cultures'))->assertForbidden();
});

test('bottles: negative, blind subculture and its reading before time, with confirmation', function () {
    receiveBloodOrder($this->assistant, ['H-1', 'H-2']);
    $automated = loadBottleFor($this->assistant, 'H-1');
    $manual = loadBottleFor($this->assistant, 'H-2', 'manual');
    $early = ['early_confirmed' => true, 'early_reason' => 'Carga del frasco registrada tarde.'];

    $this->travel(2)->hours();
    $this->actingAs($this->biochemist)->post(route('bottles.negative', $automated), $early)->assertSessionHasNoErrors();
    expect($automated->refresh()->status)->toBe(BottleStatus::Negative)
        ->and(Activity::where('subject_id', $automated->id)->where('type', ActivityType::ConfirmNegativeBottle->value)->sole()->status)->toBe(ActivityStatus::Completed);

    $this->actingAs($this->assistant)->post(route('bottles.blind-subcultures.store', $manual), $early)->assertSessionHasNoErrors();
    $subculture = BlindSubculture::sole();
    $this->actingAs($this->biochemist)->post(route('blind-subcultures.read', $subculture), ['result' => 'no_growth', ...$early])->assertSessionHasNoErrors();

    expect($subculture->fresh()->result?->value)->toBe('no_growth')
        ->and(AuditEvent::where('action', 'configuration.incubation_time_not_met')->orderBy('id')->get()->map(fn (AuditEvent $e) => $e->after['action'])->all())
        ->toBe(['Frasco negativo antes de los 5 días', 'Repique n.º 1 del frasco', 'Lectura del repique n.º 1']);
});
