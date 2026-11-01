<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Orders\Models\LabOrder;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Models\Patient;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\Reception;
use App\Modules\Samples\Models\Sample;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->site = Site::factory()->create(['name' => 'Central']);
});

function staffAt(Site $site, Role $role): User
{
    $user = User::factory()->create();
    setPermissionsTeamId($site->id);
    $user->unsetRelation('roles')->assignRole($role->value);
    setPermissionsTeamId(null);

    return $user;
}

function urineSample(string $barcode, string $type = 'URI-CM'): array
{
    return [
        'barcode' => $barcode,
        'sample_type_id' => SampleType::where('code', $type)->value('id'),
        'study_ids' => [Study::where('code', 'URO')->value('id')],
        'collected_at' => now()->subHour()->toDateTimeString(),
    ];
}

function orderPayload(array $samples, array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => (string) Str::uuid(),
        'order_number' => '123456',
        'document_number' => '30111222',
        'last_name' => 'Pérez',
        'first_name' => 'Juana',
        'birth_date' => '1980-05-10',
        'sex' => 'F',
        'priority' => 'routine',
        'requesting_physician' => 'Dra. Sintética',
        'samples' => $samples,
    ], $overrides);
}

test('one reception registers an order with several samples, each with its inoculation', function () {
    $assistant = staffAt($this->site, Role::Assistant);

    $this->actingAs($assistant)
        ->post(route('reception.store'), orderPayload([urineSample('m-001'), urineSample('M-002', 'URI-SV')]))
        ->assertRedirect(route('reception.create'))
        ->assertSessionHasNoErrors();

    $order = LabOrder::sole();
    expect($order->external_number)->toBe('123456')
        ->and($order->requesting_physician)->toBe('Dra. Sintética')
        ->and($order->site_id)->toBe($this->site->id);

    $samples = Sample::with('studies.protocolVersion')->orderBy('barcode')->get();
    expect($samples->pluck('barcode')->all())->toBe(['M-001', 'M-002'])
        ->and($samples->pluck('lab_order_id')->unique()->all())->toBe([$order->id])
        ->and($samples->every(fn (Sample $s) => $s->status === SampleStatus::Received))->toBeTrue();

    $study = $samples->first()->studies->sole();
    expect($study->status)->toBe(SampleStudyStatus::Pending)
        ->and($study->protocolVersion->status)->toBe(ProtocolVersionStatus::Active);

    expect(Reception::sole()->samples()->count())->toBe(2);

    $activities = Activity::all();
    expect($activities)->toHaveCount(2)
        ->and($activities->every(fn (Activity $a) => $a->type === ActivityType::Inoculate && $a->status === ActivityStatus::Ready))->toBeTrue()
        ->and($activities->pluck('site_id')->unique()->all())->toBe([$this->site->id]);

    expect(AuditEvent::orderBy('id')->pluck('action')->all())
        ->toBe(['patient.registered', 'order.created', 'sample.received', 'sample.received']);
});

test('a sample arriving later is added to the existing order', function () {
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-001')]));
    $this->actingAs($user)
        ->post(route('reception.store'), orderPayload([urineSample('M-002')], ['priority' => 'urgent']))
        ->assertSessionHasNoErrors();

    $order = LabOrder::sole();
    expect(Sample::where('lab_order_id', $order->id)->count())->toBe(2)
        ->and(Reception::count())->toBe(2)
        // La orden conserva sus datos: la prioridad no cambia al sumar muestras.
        ->and($order->priority->value)->toBe('routine')
        ->and(AuditEvent::where('action', 'order.created')->count())->toBe(1);
});

test('an order number of another patient is rejected', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-001')]));

    $this->actingAs($user)
        ->post(route('reception.store'), orderPayload([urineSample('M-002')], ['document_number' => '99888777']))
        ->assertSessionHasErrors('document_number');

    expect(Sample::count())->toBe(1);
});

test('order numbers are per site', function () {
    $norte = Site::factory()->create();
    $this->actingAs(staffAt($this->site, Role::Assistant))->post(route('reception.store'), orderPayload([urineSample('M-001')]));
    $this->actingAs(staffAt($norte, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample('M-002')], ['document_number' => '11222333']))
        ->assertSessionHasNoErrors();

    expect(LabOrder::count())->toBe(2);
});

test('an order without number is created each time', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-001')], ['order_number' => null]));
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-002')], ['order_number' => null]));

    expect(LabOrder::count())->toBe(2)->and(Patient::count())->toBe(1);
});

test('an existing patient is reused and not modified', function () {
    $patient = Patient::factory()->create(['document_number' => '30111222', 'last_name' => 'Original']);

    $this->actingAs(staffAt($this->site, Role::Biochemist))
        ->post(route('reception.store'), orderPayload([urineSample('M-001')], ['last_name' => 'Otro']));

    expect(Patient::count())->toBe(1)
        ->and($patient->fresh()->last_name)->toBe('Original')
        ->and(LabOrder::sole()->patient_id)->toBe($patient->id);
});

test('a barcode can only be received once', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-001')]));

    $this->actingAs($user)
        ->post(route('reception.store'), orderPayload([urineSample('M-001')], ['order_number' => '999']))
        ->assertSessionHasErrors('samples.0.barcode');

    expect(Sample::count())->toBe(1)->and(Activity::count())->toBe(1);
});

test('the same barcode cannot appear twice in one reception', function () {
    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample('M-001'), urineSample('M-001')]))
        ->assertSessionHasErrors('samples.1.barcode');

    expect(Sample::count())->toBe(0);
});

test('retrying the same reception does not duplicate anything', function () {
    $user = staffAt($this->site, Role::Assistant);
    $payload = orderPayload([urineSample('M-001'), urineSample('M-002')]);

    $this->actingAs($user)->post(route('reception.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('reception.store'), $payload)->assertSessionHasNoErrors();

    expect(Reception::count())->toBe(1)
        ->and(Sample::count())->toBe(2)
        ->and(Activity::count())->toBe(2)
        ->and(AuditEvent::where('action', 'sample.received')->count())->toBe(2);
});

test('if one sample has no active protocol, nothing is received', function () {
    $noProtocol = SampleType::create(['code' => 'URI-PS', 'name' => 'Orina — punción suprapúbica']);
    $other = urineSample('M-002');
    $other['sample_type_id'] = $noProtocol->id;

    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample('M-001'), $other]))
        ->assertSessionHasErrors('samples.1.study_ids');

    expect(Sample::count())->toBe(0)
        ->and(LabOrder::count())->toBe(0)
        ->and(Patient::count())->toBe(0)
        ->and(AuditEvent::count())->toBe(0);
});

test('a draft reference template is not usable with patients', function () {
    // Como en producción: la plantilla se carga en borrador hasta que el DT la apruebe (D-0008).
    ProtocolVersion::query()->update(['status' => ProtocolVersionStatus::Draft->value]);

    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample('M-001')]))
        ->assertSessionHasErrors('samples.0.study_ids');

    expect(Sample::count())->toBe(0);
});

test('reception validates the required data', function () {
    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), ['idempotency_key' => (string) Str::uuid(), 'samples' => [[]]])
        ->assertSessionHasErrors([
            'document_number', 'last_name', 'first_name', 'sex', 'priority',
            'samples.0.barcode', 'samples.0.sample_type_id', 'samples.0.study_ids',
        ]);
});

test('only users with samples.receive can receive', function () {
    $this->actingAs(staffAt($this->site, Role::Viewer))->get(route('reception.create'))->assertForbidden();
    $this->actingAs(staffAt($this->site, Role::Admin))
        ->post(route('reception.store'), orderPayload([urineSample('M-001')]))
        ->assertForbidden();

    expect(Sample::count())->toBe(0);
});

test('the reception screen lists catalogs and today receptions', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-77'), urineSample('M-78')]));

    $this->actingAs($user)->get(route('reception.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('samples/Reception')
            ->has('sampleTypes', 2)
            ->has('receivedToday', 1)
            ->where('receivedToday.0.order_number', '123456')
            ->has('receivedToday.0.samples', 2)
            ->missing('patientMatch')
            ->missing('orderMatch'));
});

test('the order lookup returns an existing order with its samples', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), orderPayload([urineSample('M-001')]));

    $this->actingAs($user)
        ->get(route('reception.create', ['order' => '123456']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('orderMatch', fn (Assert $reload) => $reload
                ->where('orderMatch.patient.document_number', '30111222')
                ->where('orderMatch.samples.0.barcode', 'M-001')));
});

test('the patient lookup returns a known patient', function () {
    Patient::factory()->create(['document_number' => '22333444', 'last_name' => 'Gómez']);

    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->get(route('reception.create', ['document' => '22333444']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('patientMatch', fn (Assert $reload) => $reload
                ->where('patientMatch.last_name', 'Gómez')));
});

test('the lookups return nothing for unknown values', function () {
    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->get(route('reception.create', ['document' => '99999999', 'order' => 'X-1']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly(['patientMatch', 'orderMatch'], fn (Assert $reload) => $reload
                ->where('patientMatch', null)
                ->where('orderMatch', null)));
});

test('barcodes and order numbers accept the usual barcode symbols', function (string $barcode) {
    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample($barcode)], ['order_number' => '2026/123.4']))
        ->assertSessionHasNoErrors();

    expect(Sample::sole()->barcode)->toBe(strtoupper($barcode))
        ->and(LabOrder::sole()->external_number)->toBe('2026/123.4');
})->with(['123456', '123456-01', '123456.01', 'URO/0001', 'm_000123', '00012345+1']);

test('a barcode with spaces is rejected with a clear message', function () {
    $this->actingAs(staffAt($this->site, Role::Assistant))
        ->post(route('reception.store'), orderPayload([urineSample('M 001')]))
        ->assertSessionHasErrors(['samples.0.barcode' => 'El código de muestra no puede tener espacios ni letras acentuadas.']);
});

test('the document number is normalized (dots, spaces and dashes are removed)', function () {
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)
        ->post(route('reception.store'), orderPayload([urineSample('M-001')], ['document_number' => '30.111.222']))
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('reception.store'), orderPayload([urineSample('M-002')], ['document_number' => '30 111 222', 'order_number' => null]))
        ->assertSessionHasNoErrors();

    expect(Patient::sole()->document_number)->toBe('30111222');

    $this->actingAs($user)
        ->get(route('reception.create', ['document' => '30.111.222']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('patientMatch', fn (Assert $reload) => $reload->where('patientMatch.document_number', '30111222')));
});
