<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    $this->director = userWithRole($this->site, Role::TechnicalDirector);
    $this->director->forceFill(['is_clinical_approver' => true])->save();
    $this->otherDirector = userWithRole($this->site, Role::TechnicalDirector);
});

/**
 * @param  list<string>  $groups
 * @param  list<string>  $sampleTypes
 * @param  list<string>  $antibiotics
 * @return array<string, mixed>
 */
function panelPayload(string $code, array $groups, array $sampleTypes, array $antibiotics, bool $vitek = false): array
{
    return [
        'code' => $code,
        'name' => 'Panel sintético '.$code,
        'is_vitek' => $vitek,
        'is_active' => true,
        'organism_groups' => $groups,
        'sample_type_ids' => SampleType::whereIn('code', $sampleTypes)->pluck('id')->all(),
        'antibiotic_ids' => array_map(fn (string $c) => Antibiotic::where('code', $c)->value('id'), $antibiotics),
    ];
}

/** Urocultivo con un aislamiento identificado en forma definitiva. */
function identifiedIsolateIn(User $user, string $sampleType, string $organism): Isolate
{
    receiveUrineOrder($user, ['M-'.fake()->unique()->numerify('####')], sampleType: $sampleType);
    setPermissionsTeamId(test()->site->id);
    $culture = app(InoculateSampleStudy::class)->handle($user, SampleStudy::latest('id')->firstOrFail());
    test()->travel(18)->hours();
    app(RecordReading::class)->handle($user, $culture, 'POS', null, [new IsolateData('Colonias sintéticas', 'GE5', null, true)]);
    $isolate = Isolate::latest('id')->firstOrFail();
    app(IdentifyIsolate::class)->handle($user, $isolate, Organism::where('code', $organism)->value('id'), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);

    return $isolate->fresh();
}

test('el responsable define un panel para varios grupos y varias muestras, con los antibióticos en orden', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('VTK-GN', ['enterobacterales', 'acinetobacter'], ['URI-CM', 'URI-SV'], ['GEN', 'AMK', 'CIP'], vitek: true))
        ->assertSessionHasNoErrors();

    $panel = AstPanel::where('code', 'VTK-GN')->with(['antibiotics', 'sampleTypes'])->firstOrFail();
    expect($panel->is_vitek)->toBeTrue()
        ->and($panel->organism_groups->all())->toBe([OrganismGroup::Enterobacterales, OrganismGroup::Acinetobacter])
        ->and($panel->sampleTypes->pluck('code')->sort()->values()->all())->toBe(['URI-CM', 'URI-SV'])
        ->and($panel->antibiotics->pluck('code')->all())->toBe(['GEN', 'AMK', 'CIP']);

    $event = AuditEvent::where('action', 'configuration.ast_panel_created')->sole();
    expect($event->after['antibiotics'])->toBe(['GEN', 'AMK', 'CIP'])
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('los paneles los ve quien prepara la configuración, pero los define solo el responsable', function () {
    $this->actingAs($this->biochemist)->get(route('admin.ast-panels.index'))->assertOk();

    foreach ([$this->biochemist, $this->otherDirector] as $user) {
        $this->actingAs($user)
            ->post(route('admin.ast-panels.store'), panelPayload('NO-PUEDE', ['enterobacterales'], [], ['GEN']))
            ->assertForbidden();
    }
    expect(AstPanel::where('code', 'NO-PUEDE')->exists())->toBeFalse();
});

test('un panel necesita al menos un grupo y un antibiótico', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('VACIO', [], [], []))
        ->assertSessionHasErrors(['organism_groups']);
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('VACIO', ['enterobacterales'], [], []))
        ->assertSessionHasErrors(['antibiotic_ids']);
});

test('el antibiograma propone el panel de la muestra antes que el que vale para todas', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('ENT-SV', ['enterobacterales'], ['URI-SV'], ['GEN', 'CIP']))
        ->assertSessionHasNoErrors();

    $sonda = identifiedIsolateIn($this->biochemist, 'URI-SV', 'ECOL');
    expect(app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $sonda, AstMethod::Disk)->panel->code)->toBe('ENT-SV');

    // En chorro medio el panel de sonda no corresponde: queda el general.
    $chorro = identifiedIsolateIn($this->biochemist, 'URI-CM', 'ECOL');
    $panelSonda = AstPanel::where('code', 'ENT-SV')->value('id');
    expect(fn () => app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $chorro, AstMethod::Disk, $panelSonda))
        ->toThrow(ValidationException::class);
    expect(app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $chorro, AstMethod::Disk)->panel->code)->toBe('URO-ENT');
});

test('un panel VITEK se prepara como CIM', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('VTK-SV', ['enterobacterales'], ['URI-SV'], ['GEN', 'CIP'], vitek: true))
        ->assertSessionHasNoErrors();
    $isolate = identifiedIsolateIn($this->biochemist, 'URI-SV', 'ECOL');

    expect(fn () => app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk))
        ->toThrow(ValidationException::class);
    expect(app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic)->panel->code)->toBe('VTK-SV');
});

test('sin panel para el grupo en esa muestra no se puede pedir antibiograma', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('LEV-SV', ['yeast'], ['URI-SV'], ['GEN']))
        ->assertSessionHasNoErrors();

    expect(fn () => identifiedIsolateIn($this->biochemist, 'URI-CM', 'CALB'))->toThrow(ValidationException::class);
    expect(identifiedIsolateIn($this->biochemist, 'URI-SV', 'CALB')->status->value)->toBe('identified');
});

test('modificar un panel no cambia los antibiogramas ya preparados y respeta la concurrencia', function () {
    $isolate = identifiedIsolateIn($this->biochemist, 'URI-CM', 'ECOL');
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    $panel = AstPanel::where('code', 'URO-ENT')->firstOrFail();
    expect($panel->is_reference_template)->toBeTrue();

    $payload = [...panelPayload('URO-ENT', ['enterobacterales'], [], ['GEN', 'CIP']), 'name' => 'Enterobacterales — revisado', 'lock_version' => $panel->lock_version];
    $this->actingAs($this->director)->put(route('admin.ast-panels.update', $panel), $payload)->assertSessionHasNoErrors();

    $panel->refresh();
    expect($panel->name)->toBe('Enterobacterales — revisado')
        ->and($panel->is_reference_template)->toBeFalse()
        ->and($panel->antibiotics()->count())->toBe(2)
        ->and($test->fresh()->results()->count())->toBe(14);

    $event = AuditEvent::where('action', 'configuration.ast_panel_updated')->sole();
    expect($event->before['antibiotics'])->toHaveCount(14)
        ->and($event->after['antibiotics'])->toBe(['GEN', 'CIP']);

    // Versión vieja: otra persona ya lo modificó.
    $this->actingAs($this->director)->put(route('admin.ast-panels.update', $panel), $payload)->assertSessionHasErrors(['lock_version']);
});

test('el resumen del protocolo dice qué panel le toca a cada grupo en su muestra', function () {
    $this->actingAs($this->director)
        ->post(route('admin.ast-panels.store'), panelPayload('ENT-SV', ['enterobacterales'], ['URI-SV'], ['GEN']))
        ->assertSessionHasNoErrors();
    $version = ProtocolVersion::whereHas('protocol.sampleType', fn ($q) => $q->where('code', 'URI-SV'))->firstOrFail();

    $this->actingAs($this->biochemist)->get(route('admin.protocol-versions.show', $version))
        ->assertInertia(fn ($page) => $page
            ->where('version.ast_panels.0.group', 'enterobacterales')
            ->where('version.ast_panels.0.panels', fn ($panels) => collect($panels)->pluck('name')->all() === ['Panel sintético ENT-SV', 'Enterobacterales — orina', 'VITEK — gram negativos'])
            ->where('version.ast_panels', fn ($groups) => collect($groups)->firstWhere('group', 'yeast')['panels'] === []));
});
