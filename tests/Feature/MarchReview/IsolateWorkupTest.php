<?php

/*
 * Revisión de marchas (docs/revision/MARCHAS_2026-11-03.md): lo que pasa entre «hay colonias»
 * y el antibiograma queda registrado y se puede reconstruir. Datos SINTÉTICOS y puntos de
 * corte FICTICIOS.
 */

use App\Models\User;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Configuration\Data\EarlyConfirmation;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateSignificance;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\ChangeIsolateSignificance;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Actions\LoadInstrument;
use App\Modules\Identification\Actions\ReadIsolateSubculture;
use App\Modules\Identification\Actions\ReadIsolateTests;
use App\Modules\Identification\Actions\RecordIsolateTests;
use App\Modules\Identification\Actions\RecordReferralResult;
use App\Modules\Identification\Actions\ReferIsolate;
use App\Modules\Identification\Actions\SubcultureIsolate;
use App\Modules\Identification\Data\InstrumentLoadData;
use App\Modules\Identification\Data\IsolateTestInput;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identification\Enums\SubcultureResult;
use App\Modules\Identification\Models\IsolateSubculture;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Reporting\Queries\MarchQuery;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Actions\CancelSusceptibilityTest;
use App\Modules\Susceptibility\Actions\RecordSusceptibilityResults;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->seed(SyntheticBreakpointSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    $this->assistant = userWithRole($this->site, Role::Assistant);
    setPermissionsTeamId($this->site->id);
    $this->biochemist->unsetRelation('roles')->unsetRelation('permissions');
});

/** Urocultivo con un aislamiento en identificación (sin identificar todavía). */
function workupIsolate(User $user, string $significance = 'significant'): Isolate
{
    receiveUrineOrder($user, ['M-'.fake()->unique()->numerify('####')]);
    setPermissionsTeamId(test()->site->id);
    $culture = app(InoculateSampleStudy::class)->handle($user, SampleStudy::latest('id')->firstOrFail());
    test()->travel(18)->hours();
    app(RecordReading::class)->handle($user, $culture, 'POS', 'Colonias lactosa +', [
        new IsolateData('Colonias lactosa +', 'GE5', $culture->plates->first()->id, true, IsolateSignificance::from($significance)),
    ]);

    return Isolate::latest('id')->firstOrFail();
}

function catalogTest(string $code): int
{
    return IdentificationTest::where('code', $code)->value('id');
}

function openOf(ActivityType $type): ?Activity
{
    return Activity::where('type', $type->value)->whereNotIn('status', ['completed', 'cancelled'])->latest('id')->first();
}

function ecoliId(): int
{
    return Organism::where('code', 'ECOL')->value('id');
}

/**
 * @return list<ResultInput>
 */
function allValues(SusceptibilityTest $test, float $value): array
{
    return $test->fresh()->pendingResults()
        ->map(fn ($r) => new ResultInput($r->antibiotic_id, $r->method === AstMethod::Mic ? '=' : null, $value, null, null, true))
        ->all();
}

test('Gram, pruebas rápidas y bioquímicas quedan registradas con su siembra, su lectura y su resultado (B1, B2)', function () {
    $isolate = workupIsolate($this->biochemist);

    app(RecordIsolateTests::class)->handle($this->biochemist, $isolate, [
        new IsolateTestInput(catalogTest('GRAM'), 'BGN'),
        new IsolateTestInput(catalogTest('OXI'), 'NEG'),
        new IsolateTestInput(catalogTest('TSI')),
        new IsolateTestInput(catalogTest('CIT')),
    ]);

    $tests = IsolateTest::with('test')->orderBy('id')->get();
    expect($tests)->toHaveCount(4)
        ->and($tests[0]->result_label)->toBe('Bacilos gram negativos')
        ->and($tests[0]->isRead())->toBeTrue()
        ->and($tests[2]->isRead())->toBeFalse()
        ->and($tests[2]->readable_at->diffInHours($tests[2]->set_up_at, absolute: true))->toEqual(18.0);

    // Las bioquímicas sembradas aparecen para leer en la mesada de identificación.
    $reading = openOf(ActivityType::ReadIdentificationTests);
    expect($reading?->status)->toBe(ActivityStatus::Scheduled);

    // Una prueba del momento necesita el resultado.
    expect(fn () => app(RecordIsolateTests::class)->handle($this->biochemist, $isolate, [new IsolateTestInput(catalogTest('CAT'))]))
        ->toThrow(ValidationException::class);

    // Antes de la incubación pide confirmación; el Auxiliar lee bioquímicas.
    $results = [$tests[2]->id => 'AA', $tests[3]->id => 'NEG'];
    expect(fn () => app(ReadIsolateTests::class)->handle($this->assistant, $isolate, $results))->toThrow(ValidationException::class);
    $this->travel(18)->hours();
    app(ReadIsolateTests::class)->handle($this->assistant, $isolate, $results);

    expect(IsolateTest::whereNull('read_at')->count())->toBe(0)
        ->and(IsolateTest::find($tests[2]->id)->result_label)->toBe('A/A')
        ->and(openOf(ActivityType::ReadIdentificationTests))->toBeNull()
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('el antibiograma se prepara con la identificación presuntiva y se lee recién con la definitiva (A1)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Preliminary, null, null, true, null);

    // Hora real del montaje, anterior al registro.
    $setUpAt = now()->subHour();
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk, setUpAt: $setUpAt);
    expect($test->panel->code)->toBe('URO-ENT')
        ->and($test->set_up_at->format('Y-m-d H:i'))->toBe($setUpAt->format('Y-m-d H:i'))
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::InIdentification);

    $this->travel(16)->hours();
    expect(fn () => app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0)))
        ->toThrow(ValidationException::class, 'Confirmá la identificación definitiva');

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    // Ya tenía antibiograma: no se crea otra preparación.
    expect(Activity::where('type', ActivityType::AstSetup->value)->count())->toBe(0);

    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0));
    expect($isolate->fresh()->status)->toBe(IsolateStatus::Resolved);
});

test('sin permiso del protocolo, el antibiograma sigue esperando la identificación definitiva (A1)', function () {
    ProtocolVersion::query()->update(['allow_presumptive_ast' => false]);
    $isolate = workupIsolate($this->biochemist);

    expect(fn () => app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk))
        ->toThrow(ValidationException::class, 'identificación definitiva');
});

test('la definitiva sin antibiograma descarta el preparado con la presuntiva, y un descartado deja preparar otro', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Preliminary, null, null, true, null);
    $first = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);

    app(CancelSusceptibilityTest::class)->handle($this->biochemist, $first, 'Inóculo incorrecto');
    expect($first->fresh()->status)->toBe(SusceptibilityTestStatus::Cancelled)
        ->and(Activity::where('subject_type', 'susceptibility_test')->where('subject_id', $first->id)->value('status'))->toBe(ActivityStatus::Cancelled);

    $second = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    expect($second->id)->not->toBe($first->id);

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, false, 'Colonización');
    expect($second->fresh()->status)->toBe(SusceptibilityTestStatus::Cancelled)
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::Resolved)
        ->and(openOf(ActivityType::AstRead))->toBeNull();
});

test('VITEK: tarjetas de identificación y antibiograma cargadas juntas, con su hora y su resultado (B3, A1, A2)', function () {
    $isolate = workupIsolate($this->biochemist);
    $panel = AstPanel::where('code', 'VTK-REF-GN')->firstOrFail();
    $loadedAt = now()->subMinutes(30);

    $run = app(LoadInstrument::class)->handle($this->assistant, $isolate, new InstrumentLoadData(
        instrument: 'VITEK 2', idCardType: 'GN', idCardNumber: '2410-001', astCardType: 'AST-N', astCardNumber: '2410-002',
        cassette: 'C3', position: '7', suspension: '0,55 McFarland', loadedAt: $loadedAt, astPanelId: $panel->id,
    ));

    $test = SusceptibilityTest::sole();
    expect($test->instrument_run_id)->toBe($run->id)
        ->and($test->method)->toBe(AstMethod::Mic)
        ->and($test->set_up_at->format('Y-m-d H:i'))->toBe($loadedAt->format('Y-m-d H:i'))
        ->and(openOf(ActivityType::InstrumentResult))->not->toBeNull();

    // Identificación del VITEK: se asocia a la tarjeta.
    $this->travel(6)->hours();
    $id = app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Vitek, IdentificationLevel::Definitive, null, '99 % excelente', true, null);
    expect($id->instrument_run_id)->toBe($run->id)
        ->and($run->fresh()->id_result_at)->not->toBeNull()
        ->and(openOf(ActivityType::InstrumentResult))->not->toBeNull();

    // El panel VITEK no espera una incubación fija: se lee sin confirmar «antes de tiempo».
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 1.0));
    expect($run->fresh()->ast_result_at)->not->toBeNull()
        ->and(openOf(ActivityType::InstrumentResult))->toBeNull()
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::Resolved);
});

test('la incubación del antibiograma puede ser la del panel (A2)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    AstPanel::where('code', 'URO-ENT')->update(['read_min_hours' => 20, 'read_due_hours' => 26]);

    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    $read = Activity::where('type', ActivityType::AstRead->value)->sole();
    expect($read->available_at->diffInHours($test->set_up_at, absolute: true))->toEqual(20.0);

    $this->travel(18)->hours();
    expect(fn () => app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0)))
        ->toThrow(ValidationException::class);
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0), new EarlyConfirmation(true, 'Urgencia'));
    expect($test->fresh()->status)->toBe(SusceptibilityTestStatus::Completed);
});

test('una identificación no concluyente queda con el motivo y la identificación sigue pendiente (B6)', function () {
    $isolate = workupIsolate($this->biochemist);

    expect(fn () => app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, null, IdentificationMethod::Vitek, IdentificationLevel::Inconclusive, null, null, true, null))
        ->toThrow(ValidationException::class);

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, null, IdentificationMethod::Vitek, IdentificationLevel::Inconclusive, null, '45 % bajo', true, null, 'Perfil bajo: posible mezcla');
    expect($isolate->fresh()->status)->toBe(IsolateStatus::InIdentification)
        ->and(openOf(ActivityType::Identify))->not->toBeNull();
});

test('repique del aislamiento: placa propia, lectura programada y resultado (B4)', function () {
    $isolate = workupIsolate($this->biochemist);
    $medium = $isolate->culture->plates->first()->medium_id;

    $sub = app(SubcultureIsolate::class)->handle($this->biochemist, $isolate, $medium, '35 ± 2 °C', Atmosphere::Aerobic, 'No está puro');
    expect($sub->plate->source_isolate_id)->toBe($isolate->id)
        ->and(openOf(ActivityType::ReadIsolateSubculture)?->status)->toBe(ActivityStatus::Scheduled);

    $this->travel(18)->hours();
    app(ReadIsolateSubculture::class)->handle($this->biochemist, $sub, SubcultureResult::Pure, null);
    expect(IsolateSubculture::sole()->result)->toBe(SubcultureResult::Pure)
        ->and(openOf(ActivityType::ReadIsolateSubculture))->toBeNull();
});

test('probable contaminante: se identifica sin antibiograma y sale aparte en el informe (A4, C3)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, Organism::where('code', 'SEPI')->value('id'), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    expect($isolate->fresh()->status)->toBe(IsolateStatus::Identified)
        ->and(openOf(ActivityType::AstSetup))->not->toBeNull();

    app(ChangeIsolateSignificance::class)->handle($this->biochemist, $isolate, IsolateSignificance::ProbableContaminant, 'Coagulasa negativo en 1 de 2 muestras');

    $isolate->refresh();
    expect($isolate->significance)->toBe(IsolateSignificance::ProbableContaminant)
        ->and($isolate->is_significant)->toBeFalse()
        ->and($isolate->status)->toBe(IsolateStatus::Resolved)
        ->and(openOf(ActivityType::AstSetup))->toBeNull()
        ->and($isolate->culture->fresh()->status)->toBe(CultureStatus::ClosedPositive);

    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $isolate->culture->sampleStudy)->snapshot;
    expect($snapshot['isolates'])->toBe([])
        ->and($snapshot['other_isolates'][0]['organism'])->toBe('Staphylococcus epidermidis')
        ->and($snapshot['other_isolates'][0]['significance_label'])->toBe('Probable contaminante');
});

test('cambiar a significativo un no significativo lo vuelve a identificación (A4)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(ChangeIsolateSignificance::class)->handle($this->biochemist, $isolate, IsolateSignificance::NotSignificant, 'Recuento bajo');
    expect($isolate->fresh()->status)->toBe(IsolateStatus::NotSignificant)
        ->and(openOf(ActivityType::Identify))->toBeNull();

    // Vuelve antes de cerrar: el cultivo cerró al quedar sin pendientes, así que no se puede.
    expect(fn () => app(ChangeIsolateSignificance::class)->handle($this->biochemist, $isolate, IsolateSignificance::Significant, 'Revisión'))
        ->toThrow(ValidationException::class, 'cerrado');

    expect(fn () => app(ChangeIsolateSignificance::class)->handle($this->biochemist, workupIsolate($this->biochemist), IsolateSignificance::NotSignificant, ''))
        ->toThrow(ValidationException::class);
});

test('reglas del laboratorio: informe selectivo y regla experta con una prueba de mecanismo (C2, B5)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);

    $suppress = AstRule::create(['code' => 'SEL-NIT', 'name' => 'Selectivo sintético', 'effect' => AstRuleEffect::Suppress, 'organism_groups' => [OrganismGroup::Enterobacterales]]);
    $suppress->antibiotics()->sync([Antibiotic::where('code', 'NIT')->value('id')]);
    $expert = AstRule::create([
        'code' => 'BLEE-CRO', 'name' => 'BLEE sintética', 'effect' => AstRuleEffect::Interpret, 'interpretation' => Interpretation::Resistant,
        'comment' => 'Comentario sintético', 'organism_groups' => [OrganismGroup::Enterobacterales],
        'identification_test_id' => catalogTest('BLEE'), 'test_result_code' => 'POS',
    ]);
    $expert->antibiotics()->sync([Antibiotic::where('code', 'CRO')->value('id')]);

    // Prueba de mecanismo desde el antibiograma.
    app(RecordIsolateTests::class)->handle($this->biochemist, $isolate, [new IsolateTestInput(catalogTest('BLEE'), 'POS')], $test->id);

    $this->travel(16)->hours();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0));

    $results = $test->fresh()->results->keyBy(fn ($r) => $r->antibiotic->code);
    expect($results['NIT']->is_reported)->toBeFalse()
        ->and($results['NIT']->rule_notes)->toContain('No se informa')
        ->and($results['CRO']->automatic_interpretation)->toBe(Interpretation::Susceptible)
        ->and($results['CRO']->finalInterpretation())->toBe(Interpretation::Resistant)
        ->and($results['CRO']->rule_notes)->toContain('regla experta');

    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $isolate->culture->sampleStudy)->snapshot;
    $reported = collect($snapshot['isolates'][0]['susceptibility']['results'])->keyBy('antibiotic');
    expect($reported->has('Nitrofurantoína'))->toBeFalse()
        ->and($reported['Ceftriaxona']['interpretation'])->toBe('R')
        ->and($reported['Ceftriaxona']['comment'])->toContain('Comentario sintético')
        ->and($snapshot['isolates'][0]['tests'][0])->toBe(['name' => 'Sinergia de doble disco (BLEE)', 'result' => 'Positiva: productor de BLEE']);
});

test('una prueba subrogante se informa como el antibiótico que representa (A7)', function () {
    $isolate = workupIsolate($this->biochemist);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, Organism::where('code', 'SAUR')->value('id'), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    $panel = AstPanel::create(['code' => 'STA-FOX', 'name' => 'Staphylococcus sintético', 'organism_groups' => [OrganismGroup::Staphylococcus]]);
    $panel->antibiotics()->attach(Antibiotic::where('code', 'FOX')->value('id'), ['position' => 1]);

    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk, $panel->id);
    $this->travel(16)->hours();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, [
        new ResultInput(Antibiotic::where('code', 'FOX')->value('id'), null, 25.0, Interpretation::Susceptible, null, true),
    ]);

    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $isolate->culture->sampleStudy)->snapshot;
    expect($snapshot['isolates'][0]['susceptibility']['results'][0]['antibiotic'])->toBe('Oxacilina (cefoxitina) (por Cefoxitina 30 µg)');
});

test('derivación: queda pendiente en la mesada y el resultado sale en el informe (B9)', function () {
    $isolate = workupIsolate($this->biochemist);
    $referral = app(ReferIsolate::class)->handle($this->biochemist, $isolate, 'Laboratorio de referencia sintético', 'Serogrupo');
    expect(openOf(ActivityType::ReferralResult))->not->toBeNull();

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, false, 'Sintético');
    app(RecordReferralResult::class)->handle($this->biochemist, $referral, 'Serogrupo sintético X');
    expect(openOf(ActivityType::ReferralResult))->toBeNull();

    $snapshot = app(ValidateReport::class)->handle($this->biochemist, $isolate->culture->sampleStudy)->snapshot;
    expect($snapshot['isolates'][0]['referrals'][0]['result'])->toBe('Serogrupo sintético X');
});

test('la marcha se reconstruye solo con lo registrado y se ve desde la validación', function () {
    $isolate = workupIsolate($this->biochemist);
    app(RecordIsolateTests::class)->handle($this->biochemist, $isolate, [new IsolateTestInput(catalogTest('GRAM'), 'BGN'), new IsolateTestInput(catalogTest('OXI'), 'NEG')]);
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, ecoliId(), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    $this->travel(16)->hours();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, allValues($test, 25.0));
    $study = $isolate->culture->sampleStudy;

    $march = app(MarchQuery::class)->forStudy($study);
    $titles = collect($march['timeline'])->pluck('title')->all();
    expect($titles)->toContain('Recepción', 'Siembra', 'Lectura n.º 1')
        ->and(collect($march['timeline'])->firstWhere('title', 'Siembra')['detail'])->toContain('Agar sangre')
        ->and(collect($march['isolates'][0]['steps'])->pluck('detail')->implode(' | '))->toContain('Gram de la colonia: Bacilos gram negativos', 'Oxidasa: Negativa', 'Escherichia coli')
        ->and($march['isolates'][0]['susceptibility'][0]['breakpoints'])->not->toBeNull()
        ->and($march['isolates'][0]['susceptibility'][0]['results'][0]['value'])->toContain('mm');

    $this->actingAs($this->biochemist)->get(route('marches.show', $study))
        ->assertInertia(fn (Assert $page) => $page->component('reporting/March')->has('isolates', 1)->has('timeline'));
});

test('la pantalla de identificación muestra las pruebas, el equipo y la significancia', function () {
    $isolate = workupIsolate($this->biochemist);
    $this->actingAs($this->biochemist)->post(route('isolate-tests.store', $isolate), [
        'tests' => [['test_id' => catalogTest('GRAM'), 'result_code' => 'BGN'], ['test_id' => catalogTest('TSI')]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->biochemist)->post(route('isolates.significance', $isolate), [
        'significance' => 'probable_contaminant', 'significance_reason' => 'Sintético',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->biochemist)->get(route('identifications.create', $isolate))
        ->assertInertia(fn (Assert $page) => $page->component('identification/Identify')
            ->has('tests', 2)
            ->where('isolate.significance', 'probable_contaminant')
            ->where('allowPresumptive', true)
            ->has('vitekPanels', 1)
            ->has('catalog'));
});
