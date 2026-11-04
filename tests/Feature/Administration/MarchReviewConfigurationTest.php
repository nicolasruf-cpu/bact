<?php

/*
 * Configuración de la revisión de marchas: catálogo de pruebas, reglas de antibiograma,
 * incubación por panel, carga del disco y subrogante, y los ajustes nuevos del protocolo.
 */

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->site = Site::factory()->create();
    $this->director = userWithRole($this->site, Role::TechnicalDirector);
    $this->director->forceFill(['is_clinical_approver' => true])->save();
    $this->otherDirector = userWithRole($this->site, Role::TechnicalDirector);
    $this->admin = userWithRole($this->site, Role::Admin);
});

test('el responsable define una prueba de identificación; quien no es responsable solo la ve', function () {
    $payload = [
        'code' => 'HIP', 'name' => 'Hipurato', 'kind' => 'biochemical', 'incubation_hours' => 2,
        'is_reportable' => false, 'is_active' => true,
        'result_options' => [['code' => '', 'label' => 'Positivo'], ['code' => 'NEG', 'label' => 'Negativo']],
    ];

    $this->actingAs($this->otherDirector)->get(route('admin.identification-tests.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/identification-tests/Index')->where('can.edit', false));
    $this->actingAs($this->otherDirector)->post(route('admin.identification-tests.store'), $payload)->assertForbidden();

    $this->actingAs($this->director)->post(route('admin.identification-tests.store'), $payload)->assertSessionHasNoErrors();
    $test = IdentificationTest::where('code', 'HIP')->firstOrFail();
    expect($test->result_options[0]['code'])->toBe('POSITIVO')
        ->and($test->incubation_hours)->toBe(2)
        ->and(AuditEvent::where('action', 'configuration.identification_test_created')->exists())->toBeTrue();

    // Un solo resultado no alcanza.
    $this->actingAs($this->director)->post(route('admin.identification-tests.store'), [...$payload, 'code' => 'X1', 'result_options' => [['code' => 'A', 'label' => 'A']]])
        ->assertSessionHasErrors('result_options');
});

test('el responsable define una regla experta condicionada a una prueba', function () {
    $blee = IdentificationTest::where('code', 'BLEE')->firstOrFail();
    $payload = [
        'code' => 'R-1', 'name' => 'Regla sintética', 'effect' => 'interpret', 'interpretation' => 'R', 'comment' => null,
        'organism_groups' => ['enterobacterales'], 'sample_type_ids' => [],
        'antibiotic_ids' => [Antibiotic::where('code', 'CRO')->value('id')],
        'identification_test_id' => $blee->id, 'test_result_code' => 'POS', 'is_active' => true,
    ];

    $this->actingAs($this->director)->post(route('admin.ast-rules.store'), [...$payload, 'interpretation' => null])
        ->assertSessionHasErrors('interpretation');
    $this->actingAs($this->director)->post(route('admin.ast-rules.store'), [...$payload, 'test_result_code' => 'NOPE'])
        ->assertSessionHasErrors('test_result_code');
    $this->actingAs($this->director)->post(route('admin.ast-rules.store'), $payload)->assertSessionHasNoErrors();

    $rule = AstRule::where('code', 'R-1')->firstOrFail();
    expect($rule->effect)->toBe(AstRuleEffect::Interpret)
        ->and($rule->antibiotics)->toHaveCount(1);

    $this->actingAs($this->director)->put(route('admin.ast-rules.update', $rule), [...$payload, 'is_active' => false, 'lock_version' => 0])
        ->assertSessionHasNoErrors();
    expect($rule->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->otherDirector)->get(route('admin.ast-rules.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/ast-rules/Index')->has('rules', 1)->where('can.edit', false));
});

test('un panel puede tener su propia incubación (A2)', function () {
    $panel = AstPanel::where('code', 'URO-ENT')->with(['antibiotics', 'sampleTypes'])->firstOrFail();
    $payload = [
        'name' => $panel->name, 'is_vitek' => false, 'is_active' => true,
        'organism_groups' => ['enterobacterales'], 'sample_type_ids' => [],
        'antibiotic_ids' => $panel->antibiotics->pluck('id')->all(),
        'read_min_hours' => null, 'read_due_hours' => 20, 'lock_version' => $panel->lock_version,
    ];

    $this->actingAs($this->director)->put(route('admin.ast-panels.update', $panel), $payload)->assertSessionHasErrors('read_min_hours');
    $this->actingAs($this->director)->put(route('admin.ast-panels.update', $panel), [...$payload, 'read_min_hours' => 18])->assertSessionHasNoErrors();
    expect($panel->fresh()->read_min_hours)->toBe(18);
});

test('el antibiótico guarda la carga del disco y el antibiótico que se informa (A7)', function () {
    $oxa = Antibiotic::where('code', 'OXA')->value('id');
    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'antibiotics'), [
        'code' => 'FOX2', 'name' => 'Cefoxitina sintética', 'is_active' => true, 'disk_content' => '30 µg', 'reported_as_antibiotic_id' => $oxa,
    ])->assertSessionHasNoErrors();

    $fox = Antibiotic::where('code', 'FOX2')->firstOrFail();
    expect($fox->disk_content)->toBe('30 µg')
        ->and($fox->reported_as_antibiotic_id)->toBe($oxa)
        ->and($fox->displayName())->toBe('Cefoxitina sintética 30 µg');

    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'antibiotics'), [
        'id' => $fox->id, 'name' => $fox->name, 'is_active' => true, 'disk_content' => '30 µg', 'reported_as_antibiotic_id' => $fox->id, 'lock_version' => 0,
    ])->assertSessionHasErrors('reported_as_antibiotic_id');
});

test('el borrador de protocolo guarda las horas por placa, el antibiograma presuntivo y los textos de lectura (A1, A3, A6, C1)', function () {
    $this->actingAs($this->director)->post(route('admin.protocol-versions.draft', ProtocolVersion::first()))->assertSessionHasNoErrors();
    $draft = ProtocolVersion::where('status', ProtocolVersionStatus::Draft->value)->firstOrFail();

    $content = $this->actingAs($this->director)->get(route('admin.protocol-versions.show', $draft))->viewData('page')['props']['version']['content'];
    expect($content['allow_presumptive_ast'])->toBeTrue();

    $content['allow_presumptive_ast'] = false;
    $content['report_non_significant'] = false;
    $content['no_new_growth_label'] = 'Sin desarrollo de colonias nuevas';
    $content['media'][1]['read_from_hours'] = 18;
    $content['media'][1]['read_until_hours'] = 10;

    $this->actingAs($this->director)->put(route('admin.protocol-versions.update', $draft), ['content' => $content, 'lock_version' => $draft->lock_version])
        ->assertSessionHasErrors('media.1.read_until_hours');

    $content['media'][1]['read_until_hours'] = 18;
    $this->actingAs($this->director)->put(route('admin.protocol-versions.update', $draft), ['content' => $content, 'lock_version' => $draft->lock_version])
        ->assertSessionHasNoErrors();

    $draft->refresh();
    expect($draft->allow_presumptive_ast)->toBeFalse()
        ->and($draft->report_non_significant)->toBeFalse()
        ->and($draft->no_new_growth_label)->toBe('Sin desarrollo de colonias nuevas')
        ->and($draft->media[1]->read_until_hours)->toBe(18);
});
