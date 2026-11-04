<?php

use App\Models\User;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Actions\IssuePreliminaryReport;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Actions\RecordSusceptibilityResults;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Data\AdditionalAntibiotic;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Enums\SusceptibilityTestStatus;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->seed(SyntheticBreakpointSeeder::class);
    $this->site = Site::factory()->create();
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
    setPermissionsTeamId($this->site->id);
    $this->biochemist->unsetRelation('roles')->unsetRelation('permissions');

    // Panel VITEK sintético; FOX (cefoxitina) no está en el panel y no tiene punto de corte para Enterobacterales.
    $this->vitek = AstPanel::create(['code' => 'VTK-ENT', 'name' => 'VITEK sintético', 'organism_groups' => [OrganismGroup::Enterobacterales], 'is_vitek' => true]);
    foreach (['GEN', 'CIP'] as $i => $code) {
        $this->vitek->antibiotics()->attach(Antibiotic::where('code', $code)->value('id'), ['position' => $i + 1]);
    }
    $this->tzp = Antibiotic::where('code', 'FOX')->value('id');
    $this->amk = Antibiotic::where('code', 'AMK')->value('id');
});

function ecoliIsolate(User $user): Isolate
{
    receiveUrineOrder($user, ['M-'.fake()->unique()->numerify('####')]);
    setPermissionsTeamId(test()->site->id);
    $culture = app(InoculateSampleStudy::class)->handle($user, SampleStudy::latest('id')->firstOrFail());
    test()->travel(18)->hours();
    app(RecordReading::class)->handle($user, $culture, 'POS', null, [new IsolateData('BGN', 'GE5', null, true)]);
    $isolate = Isolate::latest('id')->firstOrFail();
    app(IdentifyIsolate::class)->handle($user, $isolate, Organism::where('code', 'ECOL')->value('id'), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);

    return $isolate->fresh();
}

/**
 * @return list<ResultInput>
 */
function readPending(SusceptibilityTest $test, float $value, ?Interpretation $reviewed = null): array
{
    return $test->fresh()->pendingResults()
        ->map(fn ($r) => new ResultInput($r->antibiotic_id, $r->method === AstMethod::Mic ? '=' : null, $value, $reviewed, null, true))
        ->all();
}

test('al preparar se agregan antibióticos por fuera del panel, con su método, y se leen junto con el panel', function () {
    $isolate = ecoliIsolate($this->biochemist);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic, $this->vitek->id, [
        new AdditionalAntibiotic($this->amk, AstMethod::Disk, 'Pedido del médico'),
    ]);

    $results = $test->results()->with('antibiotic')->get();
    expect($results->pluck('antibiotic.code')->all())->toBe(['GEN', 'CIP', 'AMK'])
        ->and($results->pluck('method')->all())->toBe([AstMethod::Mic, AstMethod::Mic, AstMethod::Disk])
        ->and($results->last()->is_additional)->toBeTrue()
        ->and($results->last()->addition_reason)->toBe('Pedido del médico');

    $this->travel(16)->hours();
    // CIM 0,5 (S con el punto de corte de CIM) y halo 25 mm (S con el de difusión).
    $inputs = $test->fresh()->pendingResults()->map(fn ($r) => new ResultInput($r->antibiotic_id, $r->method === AstMethod::Mic ? '=' : null, $r->method === AstMethod::Mic ? 0.5 : 25.0, null, null, true))->all();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, $inputs);

    $test->refresh();
    expect($test->status)->toBe(SusceptibilityTestStatus::Completed)
        ->and($test->results->every(fn ($r) => $r->finalInterpretation() === Interpretation::Susceptible))->toBeTrue()
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::Resolved);
});

test('al leer el VITEK se agregan antibióticos con su propia lectura; mientras tanto, preliminar', function () {
    $isolate = ecoliIsolate($this->biochemist);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic, $this->vitek->id);
    $this->travel(16)->hours();

    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, readPending($test, 8.0), additional: [
        new AdditionalAntibiotic($this->tzp, AstMethod::Disk),
    ]);

    $test->refresh();
    expect($test->status)->toBe(SusceptibilityTestStatus::SetUp)
        ->and($test->pendingResults()->pluck('antibiotic_id')->all())->toBe([$this->tzp])
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::Identified)
        ->and($isolate->culture->fresh()->status)->toBe(CultureStatus::Workup);

    $reads = Activity::where('type', ActivityType::AstRead->value)->orderBy('id')->get();
    expect($reads)->toHaveCount(2)
        ->and($reads[0]->status)->toBe(ActivityStatus::Completed)
        ->and($reads[1]->status)->toBe(ActivityStatus::Scheduled)
        ->and($reads[1]->available_at->format('Y-m-d H:i'))->toBe(now()->addHours(16)->format('Y-m-d H:i'));

    // Preliminar: lo del VITEK, y el aviso de lo que falta.
    $study = $isolate->culture->sampleStudy;
    $susceptibility = app(IssuePreliminaryReport::class)->handle($this->biochemist, $study)->snapshot['isolates'][0]['susceptibility'];
    expect(collect($susceptibility['results'])->pluck('antibiotic')->all())->toBe(['Gentamicina', 'Ciprofloxacina'])
        ->and($susceptibility['pending'])->toBe(1);

    // Sin punto de corte para FOX: la interpretación la ingresa el bacteriólogo.
    $this->travel(16)->hours();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, readPending($test, 22.0, Interpretation::Susceptible));

    $test->refresh();
    expect($test->status)->toBe(SusceptibilityTestStatus::Completed)
        ->and($test->results->firstWhere('antibiotic_id', $this->tzp)->reviewed_interpretation)->toBe(Interpretation::Susceptible)
        ->and($isolate->fresh()->status)->toBe(IsolateStatus::Resolved)
        ->and(Activity::where('type', ActivityType::AstRead->value)->where('status', '!=', ActivityStatus::Completed->value)->count())->toBe(0);
});

test('la lectura de los agregados avisa si todavía no se cumplió la incubación', function () {
    $isolate = ecoliIsolate($this->biochemist);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic, $this->vitek->id);
    $this->travel(16)->hours();
    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, readPending($test, 8.0), additional: [new AdditionalAntibiotic($this->tzp, AstMethod::Disk)]);

    expect(fn () => app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, readPending($test, 22.0, Interpretation::Susceptible)))
        ->toThrow(ValidationException::class);
});

test('no se agrega un antibiótico que ya está ni uno repetido', function () {
    $isolate = ecoliIsolate($this->biochemist);
    $gen = Antibiotic::where('code', 'GEN')->value('id');

    expect(fn () => app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic, $this->vitek->id, [new AdditionalAntibiotic($gen, AstMethod::Disk)]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Mic, $this->vitek->id, [
        new AdditionalAntibiotic($this->tzp, AstMethod::Disk), new AdditionalAntibiotic($this->tzp, AstMethod::Mic),
    ]))->toThrow(ValidationException::class);
    expect(SusceptibilityTest::count())->toBe(0);
});

test('la pantalla manda los agregados al preparar y al leer', function () {
    $isolate = ecoliIsolate($this->biochemist);

    $this->actingAs($this->biochemist)->post(route('susceptibility.setup', $isolate), [
        'method' => 'mic',
        'panel_id' => $this->vitek->id,
        'additional' => [['antibiotic_id' => $this->amk, 'method' => 'disk', 'reason' => null]],
    ])->assertSessionHasNoErrors();
    $test = SusceptibilityTest::sole();
    expect($test->results()->count())->toBe(3);

    $this->travel(16)->hours();
    $results = $test->pendingResults()->map(fn ($r) => [
        'antibiotic_id' => $r->antibiotic_id, 'mic_operator' => $r->method === AstMethod::Mic ? '=' : null,
        'value' => $r->method === AstMethod::Mic ? 0.5 : 25, 'reviewed' => null, 'override_reason' => null, 'is_reported' => true,
    ])->all();
    $this->actingAs($this->biochemist)->post(route('susceptibility.read', $isolate), [
        'results' => $results,
        'additional' => [['antibiotic_id' => $this->tzp, 'method' => 'disk', 'reason' => 'Resistencia a aminoglucósidos']],
    ])->assertSessionHasNoErrors();

    expect($test->fresh()->pendingResults()->sole()->addition_reason)->toBe('Resistencia a aminoglucósidos');
    $this->actingAs($this->biochemist)->get(route('susceptibility.show', $isolate))
        ->assertInertia(fn ($page) => $page
            ->where('test.status', 'set_up')
            ->where('test.results', fn ($r) => collect($r)->where('read', false)->pluck('antibiotic_id')->all() === [$this->tzp]));
});
