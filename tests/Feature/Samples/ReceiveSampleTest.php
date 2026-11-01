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
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Models\Patient;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
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

function receptionPayload(array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => (string) Str::uuid(),
        'barcode' => 'M-'.fake()->unique()->numerify('######'),
        'document_number' => '30111222',
        'last_name' => 'Pérez',
        'first_name' => 'Juana',
        'birth_date' => '1980-05-10',
        'sex' => 'F',
        'sample_type_id' => SampleType::where('code', 'URI-CM')->value('id'),
        'study_ids' => [Study::where('code', 'URO')->value('id')],
        'priority' => 'routine',
        'collected_at' => now()->subHour()->toDateTimeString(),
    ], $overrides);
}

test('receiving a sample creates patient, order, sample and an inoculation activity', function () {
    $assistant = staffAt($this->site, Role::Assistant);

    $this->actingAs($assistant)
        ->post(route('reception.store'), receptionPayload(['barcode' => 'm-000123']))
        ->assertRedirect(route('reception.create'))
        ->assertSessionHasNoErrors();

    $sample = Sample::with('studies.protocolVersion')->sole();
    expect($sample->barcode)->toBe('M-000123')
        ->and($sample->status)->toBe(SampleStatus::Received)
        ->and($sample->origin_site_id)->toBe($this->site->id)
        ->and($sample->received_by)->toBe($assistant->id);

    $study = $sample->studies->sole();
    expect($study->status)->toBe(SampleStudyStatus::Pending)
        ->and($study->protocolVersion->status)->toBe(ProtocolVersionStatus::Active)
        ->and($study->processing_site_id)->toBe($this->site->id);

    $activity = Activity::sole();
    expect($activity->type)->toBe(ActivityType::Inoculate)
        ->and($activity->status)->toBe(ActivityStatus::Ready)
        ->and($activity->site_id)->toBe($this->site->id)
        ->and($activity->subject_type)->toBe('sample_study')
        ->and($activity->subject_id)->toBe($study->id);

    expect(AuditEvent::pluck('action')->all())->toBe(['patient.registered', 'sample.received']);
});

test('an existing patient is reused and not modified', function () {
    $patient = Patient::factory()->create(['document_number' => '30111222', 'last_name' => 'Original']);
    $user = staffAt($this->site, Role::Biochemist);

    $this->actingAs($user)->post(route('reception.store'), receptionPayload(['last_name' => 'Otro']));

    expect(Patient::count())->toBe(1)
        ->and($patient->fresh()->last_name)->toBe('Original')
        ->and(Sample::sole()->labOrder->patient_id)->toBe($patient->id);
});

test('a barcode can only be received once', function () {
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)->post(route('reception.store'), receptionPayload(['barcode' => 'M-1']));
    $this->actingAs($user)->post(route('reception.store'), receptionPayload(['barcode' => 'M-1']))
        ->assertSessionHasErrors('barcode');

    expect(Sample::count())->toBe(1)->and(Activity::count())->toBe(1);
});

test('retrying with the same idempotency key does not duplicate anything', function () {
    $user = staffAt($this->site, Role::Assistant);
    $payload = receptionPayload();

    $this->actingAs($user)->post(route('reception.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('reception.store'), $payload)->assertSessionHasNoErrors();

    expect(Sample::count())->toBe(1)
        ->and(Activity::count())->toBe(1)
        ->and(AuditEvent::where('action', 'sample.received')->count())->toBe(1);
});

test('a study without an active protocol cannot be received', function () {
    $user = staffAt($this->site, Role::Assistant);
    $draftOnly = SampleType::create(['code' => 'URI-PS', 'name' => 'Orina — punción suprapúbica']);

    $this->actingAs($user)
        ->post(route('reception.store'), receptionPayload(['sample_type_id' => $draftOnly->id]))
        ->assertSessionHasErrors('study_ids');

    expect(Sample::count())->toBe(0)
        ->and(Patient::count())->toBe(0)
        ->and(AuditEvent::count())->toBe(0);
});

test('a draft reference template is not usable with patients', function () {
    // Como en producción: la plantilla se carga en borrador hasta que el DT la apruebe (D-0008).
    ProtocolVersion::query()->update(['status' => ProtocolVersionStatus::Draft->value]);
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)
        ->post(route('reception.store'), receptionPayload())
        ->assertSessionHasErrors('study_ids');

    expect(Sample::count())->toBe(0);
});

test('reception validates the required data', function () {
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)
        ->post(route('reception.store'), ['idempotency_key' => (string) Str::uuid()])
        ->assertSessionHasErrors(['barcode', 'document_number', 'last_name', 'first_name', 'sex', 'sample_type_id', 'study_ids', 'priority']);
});

test('only users with samples.receive can receive', function () {
    $viewer = staffAt($this->site, Role::Viewer);
    $admin = staffAt($this->site, Role::Admin);

    $this->actingAs($viewer)->get(route('reception.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('reception.store'), receptionPayload())->assertForbidden();

    expect(Sample::count())->toBe(0);
});

test('the reception screen lists catalogs and today receptions', function () {
    $user = staffAt($this->site, Role::Assistant);
    $this->actingAs($user)->post(route('reception.store'), receptionPayload(['barcode' => 'M-77']));

    $this->actingAs($user)->get(route('reception.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('samples/Reception')
            ->has('sampleTypes', 2)
            ->has('receivedToday', 1)
            ->where('receivedToday.0.barcode', 'M-77')
            ->missing('patientMatch'));
});

test('the patient lookup returns a known patient', function () {
    Patient::factory()->create(['document_number' => '22333444', 'last_name' => 'Gómez']);
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)
        ->get(route('reception.create', ['document' => '22333444']))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('patientMatch')
            ->reloadOnly('patientMatch', fn (Assert $reload) => $reload
                ->where('patientMatch.last_name', 'Gómez')
                ->where('patientMatch.document_number', '22333444')));
});

test('the patient lookup returns nothing for an unknown document', function () {
    $user = staffAt($this->site, Role::Assistant);

    $this->actingAs($user)
        ->get(route('reception.create', ['document' => '99999999']))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('patientMatch', fn (Assert $reload) => $reload->where('patientMatch', null)));
});
