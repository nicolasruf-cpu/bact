<?php

/*
 * Revisión de marchas: lectura por placa (A3), textos de las lecturas sin desarrollo (A6),
 * placa perdida (B7), muestra no apta (B8), directo con valores (B10), recuento sin
 * aislamientos (C1) y frascos del set de hemocultivo (A5). Datos SINTÉTICOS.
 */

use App\Models\User;
use App\Modules\BloodCultures\Actions\LoadBottle;
use App\Modules\BloodCultures\Actions\RecordBottlePositive;
use App\Modules\BloodCultures\Actions\SubcultureBottle;
use App\Modules\BloodCultures\Enums\BottleMethod;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Database\Seeders\BloodCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\PlateCultureTemplateSeeder;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\MarkPlateLost;
use App\Modules\Cultures\Actions\RecordDirectExam;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Actions\RejectSampleStudy;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Data\PlateObservationData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\PlateObservation;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\PlateReading;
use App\Modules\Cultures\Models\Reading;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    app(PlateCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    app(BloodCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    setPermissionsTeamId($this->site->id);
    $this->biochemist->unsetRelation('roles')->unsetRelation('permissions');
});

function receiveStudy(User $user, string $study, string $sampleType): SampleStudy
{
    $barcode = 'M-'.fake()->unique()->numerify('#####');
    test()->actingAs($user)->post(route('reception.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'document_number' => fake()->unique()->numerify('########'),
        'last_name' => 'Sintético',
        'first_name' => 'Paciente',
        'sex' => 'U',
        'priority' => 'routine',
        'samples' => [[
            'barcode' => $barcode,
            'sample_type_id' => SampleType::where('code', $sampleType)->value('id'),
            'study_ids' => [Study::where('code', $study)->value('id')],
        ]],
    ])->assertSessionHasNoErrors();
    setPermissionsTeamId(test()->site->id);

    return SampleStudy::query()->whereHas('sample', fn ($q) => $q->where('barcode', $barcode))->firstOrFail();
}

function inoculated(User $user, string $study, string $sampleType): Culture
{
    return app(InoculateSampleStudy::class)->handle($user, receiveStudy($user, $study, $sampleType));
}

function plateOf(Culture $culture, string $code): Plate
{
    return $culture->plates()->with('medium')->get()->first(fn (Plate $p) => $p->medium->code === $code);
}

test('cada lectura registra qué se vio en cada placa, y una placa se lee solo en sus horas (A3)', function () {
    $culture = inoculated($this->biochemist, 'COPRO', 'MF');
    $campy = plateOf($culture, 'CAMPY');
    expect($campy->read_from_hours)->toBe(42);

    $this->travel(18)->hours();
    // Campylobacter todavía no se lee.
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null, [], new EarlyConfirmation, [
        new PlateObservationData($campy->id, PlateObservation::NoGrowth),
    ]))->toThrow(ValidationException::class, 'no se lee');

    // Sin desarrollo con una placa con desarrollo: es una contradicción.
    $mac = plateOf($culture, 'MAC');
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null, [], new EarlyConfirmation, [
        new PlateObservationData($mac->id, PlateObservation::Growth),
    ]))->toThrow(ValidationException::class);

    $reading = app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null);
    $media = $reading->plateReadings()->with('plate.medium')->get()->map(fn (PlateReading $p) => $p->plate->medium->code)->sort()->values()->all();
    expect($media)->toBe(['MAC', 'SS', 'XLD'])
        ->and($reading->plateReadings->every(fn (PlateReading $p) => $p->observation === PlateObservation::NoGrowth))->toBeTrue()
        // La intermedia sin desarrollo no usa el texto del negativo final (A6).
        ->and($reading->label)->toBe('Sin desarrollo hasta el momento');
});

test('con desarrollo, el aislamiento sale de la placa con desarrollo; después, «sin desarrollo nuevo» tiene su texto (A3, A6)', function () {
    $culture = inoculated($this->biochemist, 'FAU', 'HFA');
    $as = plateOf($culture, 'AS');
    $this->travel(18)->hours();

    app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, [new IsolateData('Colonias beta hemolíticas', null, $as->id, true)], new EarlyConfirmation, [
        new PlateObservationData($as->id, PlateObservation::Growth, 'Beta hemólisis'),
    ]);
    expect(PlateReading::sole()->observation)->toBe(PlateObservation::Growth)
        ->and(PlateReading::sole()->notes)->toBe('Beta hemólisis');

    $this->travel(24)->hours();
    $second = app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null);
    expect($second->label)->toBe('Sin desarrollo nuevo')
        ->and($second->plateReadings->first()->observation)->toBe(PlateObservation::NoNewGrowth);

    $this->actingAs($this->biochemist)->get(route('readings.create', $culture))->assertOk();
});

test('una placa perdida se da de baja con el motivo y se resiembra; la lectura espera la resiembra (B7)', function () {
    $culture = inoculated($this->biochemist, 'FAU', 'HFA');
    $as = plateOf($culture, 'AS');
    $this->travel(10)->hours();

    $replacement = app(MarkPlateLost::class)->handle($this->biochemist, $as, 'Placa rota en la estufa', true);
    expect($as->fresh()->status)->toBe(PlateStatus::Lost)
        ->and($as->fresh()->lost_reason)->toBe('Placa rota en la estufa')
        ->and($replacement?->replaces_plate_id)->toBe($as->id);

    // A las 18 h de la siembra original, la resiembra todavía no cumplió su incubación.
    $this->travel(8)->hours();
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null))->toThrow(ValidationException::class);
    $this->travel(10)->hours();
    $reading = app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null);
    expect($reading->plateReadings->pluck('plate_id')->all())->toBe([$replacement->id]);
});

test('muestra no apta antes de sembrar: el informe dice el motivo y la nueva muestra pedida (B8)', function () {
    $study = receiveStudy($this->biochemist, 'RESP', 'ESP');
    app(RejectSampleStudy::class)->handle($this->biochemist, $study, 'Más de 25 células epiteliales por campo', 'Sala 3');

    expect($study->fresh()->status)->toBe(SampleStudyStatus::ReadyForValidation)
        ->and(Activity::where('type', ActivityType::Inoculate->value)->value('status'))->toBe(ActivityStatus::Cancelled)
        ->and(Activity::where('type', ActivityType::ValidateReport->value)->value('status'))->toBe(ActivityStatus::Ready);

    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $study)->snapshot;
    expect($snapshot['result'])->toBe('Muestra no apta: Más de 25 células epiteliales por campo')
        ->and($snapshot['rejection']['new_sample_requested_to'])->toBe('Sala 3');
});

test('muestra no apta con el cultivo sembrado: se cierra y se cancela lo pendiente (B8)', function () {
    $culture = inoculated($this->biochemist, 'FAU', 'HFA');
    app(RejectSampleStudy::class)->handle($this->biochemist, $culture->sampleStudy, 'Hisopo seco', null);

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedRejected)
        ->and(Activity::where('type', ActivityType::ReadCulture->value)->value('status'))->toBe(ActivityStatus::Cancelled);
    expect(fn () => app(RejectSampleStudy::class)->handle($this->biochemist, $culture->sampleStudy, 'Otra vez', null))->toThrow(ValidationException::class);
});

test('el directo admite valores numéricos (pH, Nugent) además de opciones (B10)', function () {
    $study = receiveStudy($this->biochemist, 'GEN', 'FV');
    $exam = app(RecordDirectExam::class)->handle($this->biochemist, $study, ['PH' => ['4,8'], 'NUG' => ['7'], 'AMI' => ['POS']]);

    expect($exam->results)->toBe(['PH' => ['4.8'], 'AMI' => ['POS'], 'NUG' => ['7']]);
    expect(fn () => app(RecordDirectExam::class)->handle($this->biochemist, receiveStudy($this->biochemist, 'GEN', 'FV'), ['PH' => ['ácido']]))
        ->toThrow(ValidationException::class);
});

test('un desarrollo no significativo puede llevar el recuento al informe (C1)', function () {
    $culture = inoculated($this->biochemist, 'URO', 'URI-CM');
    $this->travel(18)->hours();
    app(RecordReading::class)->handle($this->biochemist, $culture, 'NS', null, [], new EarlyConfirmation, [], 'LT3');

    expect(Reading::sole()->count_category)->toBe('LT3');
    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $culture->sampleStudy)->snapshot;
    expect($snapshot['result'])->toBe('Desarrollo no significativo')
        ->and($snapshot['result_count'])->toBe('< 10³ UFC/mL');
});

test('hemocultivo: el segundo frasco del set es otra muestra, y el anaerobio se subcultiva en anaerobiosis (A5)', function () {
    $study = receiveStudy($this->biochemist, 'HEMO', 'SAN-PER');
    $bottle = app(LoadBottle::class)->handle($this->biochemist, $study, BottleMethod::Automated, 'AER', 'BacT/ALERT');

    // Repetir la misma carga devuelve el frasco; otro frasco en la misma muestra avisa.
    expect(app(LoadBottle::class)->handle($this->biochemist, $study, BottleMethod::Automated, 'AER', 'BacT/ALERT')->id)->toBe($bottle->id);
    expect(fn () => app(LoadBottle::class)->handle($this->biochemist, $study, BottleMethod::Automated, 'ANA', 'BacT/ALERT'))
        ->toThrow(ValidationException::class, 'muestra propia');

    $anaerobic = app(LoadBottle::class)->handle($this->biochemist, receiveStudy($this->biochemist, 'HEMO', 'SAN-PER'), BottleMethod::Automated, 'ANA', 'BacT/ALERT');
    foreach ([$bottle, $anaerobic] as $b) {
        app(RecordBottlePositive::class)->handle($this->biochemist, $b);
        app(SubcultureBottle::class)->handle($this->biochemist, $b->fresh());
    }

    $media = fn (int $studyId) => Culture::where('sample_study_id', $studyId)->sole()->plates()->with('medium')->get()->map(fn (Plate $p) => $p->medium->code)->sort()->values()->all();
    expect($media($bottle->sample_study_id))->toBe(['AS', 'CHOC', 'MAC'])
        ->and($media($anaerobic->sample_study_id))->toBe(['AS', 'BRU', 'CHOC', 'MAC']);
});

test('la pantalla de lectura muestra las placas que tocan y las que no', function () {
    $culture = inoculated($this->biochemist, 'COPRO', 'MF');
    $this->travel(18)->hours();

    $this->actingAs($this->biochemist)->get(route('readings.create', $culture))
        ->assertInertia(fn (Assert $page) => $page->component('cultures/Reading')
            ->where('culture.plates', fn ($plates) => collect($plates)->firstWhere('code', 'CAMPY')['due'] === false
                && collect($plates)->firstWhere('code', 'MAC')['due'] === true)
            ->has('significances', 3));
});
