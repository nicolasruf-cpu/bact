<?php

use App\Models\User;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Configuration\Services\ActiveBreakpointSet;
use App\Modules\Configuration\Services\ActiveProtocolResolver;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    $this->director = userWithRole($this->site, Role::TechnicalDirector);
    $this->director->forceFill(['is_clinical_approver' => true])->save();
    $this->otherDirector = userWithRole($this->site, Role::TechnicalDirector);
    $this->active = ProtocolVersion::query()->where('status', 'active')
        ->whereHas('protocol.sampleType', fn ($q) => $q->where('code', 'URI-CM'))->firstOrFail();
});

/**
 * Contenido del editor tal como lo manda la pantalla.
 *
 * @return array<string, mixed>
 */
function editorContent(ProtocolVersion $version): array
{
    $version->load('media');

    return [
        'notes' => 'Ajuste sintético de prueba',
        'source' => $version->source,
        'reading_schedule' => [['min_hours' => 16, 'due_hours' => 24]],
        'reincubation_hours' => 24,
        'max_reincubations' => 1,
        'allow_bulk_negative_confirmation' => false,
        'reading_options' => $version->reading_options,
        'count_categories' => $version->count_categories,
        'ast_read_min_hours' => 16,
        'ast_read_due_hours' => 24,
        'media' => $version->media->map(fn ($m) => [
            'medium_id' => $m->medium_id,
            'inoculum' => $m->inoculum,
            'incubation_temperature' => $m->incubation_temperature,
            'atmosphere' => $m->atmosphere->value,
            'is_enrichment' => false,
            'enrichment_subculture_min_hours' => null,
            'enrichment_subculture_due_hours' => null,
            'enrichment_subculture_media' => [],
        ])->all(),
        'direct_exam_settings' => null,
        'blood_culture_settings' => null,
    ];
}

function draftFrom(User $user, ProtocolVersion $version): ProtocolVersion
{
    test()->actingAs($user)->post(route('admin.protocol-versions.draft', $version))->assertSessionHasNoErrors();

    return ProtocolVersion::where('protocol_id', $version->protocol_id)->where('status', 'draft')->firstOrFail();
}

test('una versión nueva pasa por borrador, aprobación y vigencia; la anterior se retira', function () {
    $draft = draftFrom($this->biochemist, $this->active);
    expect($draft->version)->toBe(2)->and($draft->media)->toHaveCount(2);

    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $draft), [
        'lock_version' => 0, 'content' => editorContent($draft),
    ])->assertSessionHasNoErrors();
    expect($draft->fresh()->first_read_min_hours)->toBe(16);

    // El Bioquímico prepara, pero no aprueba.
    $this->actingAs($this->biochemist)->post(route('admin.protocol-versions.approve', $draft), ['lock_version' => 1])->assertForbidden();
    // Un Director técnico que no es el responsable tampoco.
    $this->actingAs($this->otherDirector)->post(route('admin.protocol-versions.approve', $draft), ['lock_version' => 1])->assertForbidden();

    $this->actingAs($this->director)->post(route('admin.protocol-versions.approve', $draft), ['lock_version' => 1, 'note' => 'Revisado'])
        ->assertSessionHasNoErrors();
    expect($draft->fresh()->status)->toBe(ProtocolVersionStatus::Approved)
        ->and($draft->fresh()->approved_by)->toBe($this->director->id);

    // Aprobada, todavía no reemplaza a la vigente.
    $resolver = app(ActiveProtocolResolver::class);
    $protocol = $draft->protocol;
    expect($resolver->resolve($protocol->study_id, $protocol->sample_type_id)->id)->toBe($this->active->id);

    $this->actingAs($this->director)->post(route('admin.protocol-versions.activate', $draft), ['lock_version' => 2])
        ->assertSessionHasNoErrors();

    expect($resolver->resolve($protocol->study_id, $protocol->sample_type_id)->id)->toBe($draft->id)
        ->and($this->active->fresh()->status)->toBe(ProtocolVersionStatus::Retired);
    expect(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('las muestras recibidas conservan su versión y las nuevas usan la vigente', function () {
    receiveUrineOrder($this->biochemist, ['SINT-0001']);
    $before = SampleStudy::firstOrFail();
    expect($before->protocol_version_id)->toBe($this->active->id);

    $draft = draftFrom($this->biochemist, $this->active);
    $this->actingAs($this->director)->post(route('admin.protocol-versions.approve', $draft), ['lock_version' => 0]);
    $this->actingAs($this->director)->post(route('admin.protocol-versions.activate', $draft), ['lock_version' => 1]);

    receiveUrineOrder($this->biochemist, ['SINT-0002']);
    expect($before->fresh()->protocol_version_id)->toBe($this->active->id)
        ->and(SampleStudy::latest('id')->first()->protocol_version_id)->toBe($draft->id);
});

test('solo se modifica un borrador y una sola vez por versión de datos', function () {
    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $this->active), [
        'lock_version' => $this->active->lock_version, 'content' => editorContent($this->active),
    ])->assertSessionHasErrors('version');

    $draft = draftFrom($this->biochemist, $this->active);
    // Repetir el pedido de borrador devuelve el mismo.
    expect(draftFrom($this->biochemist, $this->active)->id)->toBe($draft->id);

    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $draft), [
        'lock_version' => 0, 'content' => editorContent($draft),
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $draft), [
        'lock_version' => 0, 'content' => editorContent($draft),
    ])->assertSessionHasErrors('lock_version');
});

test('el editor valida la forma del contenido', function () {
    $draft = draftFrom($this->biochemist, $this->active);
    $content = editorContent($draft);
    $content['reading_schedule'] = [['min_hours' => 24, 'due_hours' => 18]];
    $content['reading_options'][] = ['code' => 'NEG', 'label' => 'Repetida', 'outcome' => 'no_growth'];

    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $draft), [
        'lock_version' => 0, 'content' => $content,
    ])->assertSessionHasErrors(['reading_options.0.code']);

    $content = editorContent($draft);
    $content['reading_schedule'] = [['min_hours' => 24, 'due_hours' => 18]];
    $this->actingAs($this->biochemist)->put(route('admin.protocol-versions.update', $draft), [
        'lock_version' => 0, 'content' => $content,
    ])->assertSessionHasErrors(['reading_schedule.0.due_hours']);
});

test('un protocolo nuevo arranca vacío y no se aprueba hasta completarlo', function () {
    $study = Study::firstOrCreate(['code' => 'SINT'], ['name' => 'Estudio sintético']);
    $type = SampleType::firstOrCreate(['code' => 'SINT-T'], ['name' => 'Tipo sintético']);

    $this->actingAs($this->biochemist)->post(route('admin.protocols.store'), [
        'study_id' => $study->id, 'sample_type_id' => $type->id, 'processing_kind' => 'plate',
    ])->assertSessionHasNoErrors();

    $version = Protocol::where('study_id', $study->id)->firstOrFail()->versions()->firstOrFail();
    expect($version->status)->toBe(ProtocolVersionStatus::Draft);

    $this->actingAs($this->director)->post(route('admin.protocol-versions.approve', $version), ['lock_version' => 0])
        ->assertSessionHasErrors('version');

    // Repetido: ya existe.
    $this->actingAs($this->biochemist)->post(route('admin.protocols.store'), [
        'study_id' => $study->id, 'sample_type_id' => $type->id, 'processing_kind' => 'plate',
    ])->assertSessionHasErrors('study_id');
});

test('retirar exige motivo y el borrador lo puede descartar quien lo prepara', function () {
    $draft = draftFrom($this->biochemist, $this->active);

    $this->actingAs($this->biochemist)->post(route('admin.protocol-versions.retire', $this->active), ['lock_version' => 0, 'reason' => 'x'])
        ->assertForbidden();
    $this->actingAs($this->biochemist)->post(route('admin.protocol-versions.retire', $draft), ['lock_version' => 0, 'reason' => ''])
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->biochemist)->post(route('admin.protocol-versions.retire', $draft), ['lock_version' => 0, 'reason' => 'Lo rehago'])
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->status)->toBe(ProtocolVersionStatus::Retired);
});

test('los puntos de corte se importan por CSV, se aprueban y quedan vigentes', function () {
    $this->seed(SyntheticBreakpointSeeder::class);
    $synthetic = BreakpointSet::where('is_synthetic', true)->firstOrFail();

    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.store'), [
        'name' => 'Set sintético de prueba', 'edition' => 'PRUEBA-1',
    ])->assertSessionHasNoErrors();
    $set = BreakpointSet::where('edition', 'PRUEBA-1')->firstOrFail();

    // Valores ficticios, solo para la prueba.
    $bad = "organism_group;antibiotic_code;method;susceptible;resistant\nenterobacterales;NOEXISTE;disk;20;14\nenterobacterales;CIP;disk;10;14\n";
    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.import', $set), [
        'file' => UploadedFile::fake()->createWithContent('malo.csv', $bad), 'lock_version' => 0,
    ])->assertSessionHasErrors('file');
    expect($set->breakpoints()->count())->toBe(0);

    $good = "organism_group;antibiotic_code;method;susceptible;resistant\nenterobacterales;CIP;disk;30;20\nenterobacterales;CIP;mic;0,5;2\nenterobacterales;GEN;disk;;\n";
    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.import', $set), [
        'file' => UploadedFile::fake()->createWithContent('bueno.csv', $good), 'lock_version' => 0,
    ])->assertSessionHasNoErrors();
    expect($set->breakpoints()->count())->toBe(2);

    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.status', $set), ['status' => 'approved', 'lock_version' => 1])
        ->assertForbidden();
    $this->actingAs($this->director)->post(route('admin.breakpoint-sets.status', $set), ['status' => 'approved', 'lock_version' => 1])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->director)->post(route('admin.breakpoint-sets.status', $set), ['status' => 'active', 'lock_version' => 2])
        ->assertSessionHasNoErrors();

    // Un set real vigente tiene prioridad sobre el ficticio, que no se toca.
    expect(app(ActiveBreakpointSet::class)->resolve()->id)->toBe($set->id)
        ->and($synthetic->fresh()->status)->toBe(ProtocolVersionStatus::Active);

    // Una edición nueva retira la anterior al ponerse vigente.
    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.store'), [
        'name' => 'Set sintético 2', 'edition' => 'PRUEBA-2', 'copy_from_id' => $set->id,
    ])->assertSessionHasNoErrors();
    $next = BreakpointSet::where('edition', 'PRUEBA-2')->firstOrFail();
    expect($next->breakpoints()->count())->toBe(2);
    $this->actingAs($this->director)->post(route('admin.breakpoint-sets.status', $next), ['status' => 'approved', 'lock_version' => 0]);
    $this->actingAs($this->director)->post(route('admin.breakpoint-sets.status', $next), ['status' => 'active', 'lock_version' => 1]);

    expect($set->fresh()->status)->toBe(ProtocolVersionStatus::Retired)
        ->and(app(ActiveBreakpointSet::class)->resolve()->id)->toBe($next->id);
});

test('el set ficticio de demo no se copia ni se modifica', function () {
    $this->seed(SyntheticBreakpointSeeder::class);
    $synthetic = BreakpointSet::where('is_synthetic', true)->firstOrFail();

    $this->actingAs($this->biochemist)->post(route('admin.breakpoint-sets.store'), [
        'name' => 'Copia', 'edition' => 'X', 'copy_from_id' => $synthetic->id,
    ])->assertSessionHasErrors('copy_from_id');

    $this->actingAs($this->director)->post(route('admin.breakpoint-sets.status', $synthetic), ['status' => 'retired', 'lock_version' => 0, 'reason' => 'x'])
        ->assertSessionHasErrors('status');
});

test('la plantilla CSV sale con el encabezado y sin valores', function () {
    $response = $this->actingAs($this->biochemist)->get(route('admin.breakpoint-sets.template'));
    $response->assertOk();
    $lines = array_filter(explode("\r\n", substr($response->streamedContent(), 3)));

    expect($lines[0])->toBe('organism_group;antibiotic_code;method;susceptible;resistant');
    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toEndWith(';;');
    }
});

test('las pantallas de configuración clínica abren para quien la prepara', function () {
    $this->seed(SyntheticBreakpointSeeder::class);
    $set = BreakpointSet::firstOrFail();

    $this->actingAs($this->biochemist)->get(route('admin.protocols.index'))->assertOk();
    $this->actingAs($this->biochemist)->get(route('admin.protocol-versions.show', $this->active))->assertOk();
    $this->actingAs($this->biochemist)->get(route('admin.breakpoint-sets.index'))->assertOk();
    $this->actingAs($this->biochemist)->get(route('admin.breakpoint-sets.show', $set))->assertOk();

    $assistant = userWithRole($this->site, Role::Assistant);
    $this->actingAs($assistant)->get(route('admin.protocols.index'))->assertForbidden();
});
