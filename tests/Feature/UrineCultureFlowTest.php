<?php

use App\Models\User;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\RecordReading;
use App\Modules\Cultures\Data\IsolateData;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identification\Actions\IdentifyIsolate;
use App\Modules\Identification\Enums\IdentificationLevel;
use App\Modules\Identification\Enums\IdentificationMethod;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Actions\RecordSusceptibilityResults;
use App\Modules\Susceptibility\Actions\SetUpSusceptibilityTest;
use App\Modules\Susceptibility\Data\ResultInput;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use App\Modules\Workflow\Enums\ActivityStatus;
use App\Modules\Workflow\Enums\ActivityType;
use App\Modules\Workflow\Models\Activity;
use Illuminate\Auth\Access\AuthorizationException;
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
});

function openActivity(ActivityType $type): ?Activity
{
    return Activity::where('type', $type->value)->whereNotIn('status', ['completed', 'cancelled'])->first();
}

function receiveAndInoculate(User $user): Culture
{
    receiveUrineOrder($user, ['M-'.fake()->unique()->numerify('####')]);
    setPermissionsTeamId(test()->site->id);

    return app(InoculateSampleStudy::class)->handle($user, SampleStudy::latest('id')->firstOrFail());
}

test('negative urine culture: read, close and validate', function () {
    $culture = receiveAndInoculate($this->biochemist);

    // No se puede leer antes de la incubación mínima.
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null))
        ->toThrow(ValidationException::class);

    $this->travel(18)->hours();
    app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null);

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedNegative)
        ->and($culture->sampleStudy->fresh()->status)->toBe(SampleStudyStatus::ReadyForValidation)
        ->and(openActivity(ActivityType::ReadCulture))->toBeNull()
        ->and(openActivity(ActivityType::ValidateReport)?->status)->toBe(ActivityStatus::Ready);

    $report = app(ValidateReport::class)->handle($this->biochemist, $culture->sampleStudy);

    expect($report->snapshot['result'])->toBe('Sin desarrollo')
        ->and($report->snapshot['isolates'])->toBe([])
        ->and($report->snapshot['protocol']['is_reference_template'])->toBeTrue()
        ->and($culture->sampleStudy->fresh()->status)->toBe(SampleStudyStatus::FinalReported)
        ->and($culture->sampleStudy->sample->fresh()->status)->toBe(SampleStatus::Reported)
        ->and(openActivity(ActivityType::ValidateReport))->toBeNull()
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('positive urine culture end to end: isolate, identification, susceptibility, report', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();

    app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', 'Colonias lactosa +', [
        new IsolateData('Bacilo gram negativo, lactosa positivo', 'GE5', $culture->plates->first()->id, true),
        new IsolateData('Colonias escasas', 'LT3', null, false),
    ]);

    expect($culture->fresh()->status)->toBe(CultureStatus::Workup);
    $isolates = Isolate::orderBy('number')->get();
    expect($isolates->pluck('status')->all())->toBe([IsolateStatus::InIdentification, IsolateStatus::NotSignificant])
        ->and(Activity::where('type', ActivityType::Identify->value)->count())->toBe(1);

    $isolate = $isolates->first();
    $ecoli = Organism::where('code', 'ECOL')->firstOrFail();

    // Preliminar: la identificación sigue abierta.
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, $ecoli->id, IdentificationMethod::Biochemical, IdentificationLevel::Preliminary, 'TSI A/A', null, true, null);
    expect(openActivity(ActivityType::Identify))->not->toBeNull();

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, $ecoli->id, IdentificationMethod::Biochemical, IdentificationLevel::Definitive, 'TSI A/A, indol +', null, true, null);
    expect($isolate->fresh()->status)->toBe(IsolateStatus::Identified)
        ->and(openActivity(ActivityType::Identify))->toBeNull()
        ->and(openActivity(ActivityType::AstSetup)?->status)->toBe(ActivityStatus::Ready);

    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    expect($test->panel->code)->toBe('URO-ENT')
        ->and($test->results)->toHaveCount(14)
        ->and(openActivity(ActivityType::AstRead)?->status)->toBe(ActivityStatus::Scheduled);

    $this->travel(16)->hours();
    $this->artisan('activities:release');
    expect(openActivity(ActivityType::AstRead)?->status)->toBe(ActivityStatus::Ready);

    $amp = Antibiotic::where('code', 'AMP')->value('id');
    $cip = Antibiotic::where('code', 'CIP')->value('id');
    $nit = Antibiotic::where('code', 'NIT')->value('id');
    $inputs = $test->results->map(fn ($r) => new ResultInput(
        antibioticId: $r->antibiotic_id,
        micOperator: null,
        value: match ($r->antibiotic_id) {
            $amp => 8.0, $cip => 17.0, default => 25.0
        }, // ficticio: R, I, S
        reviewed: $r->antibiotic_id === $nit ? Interpretation::Resistant : null,
        overrideReason: $r->antibiotic_id === $nit ? 'Colonias dentro del halo' : null,
        reported: true,
    ))->all();

    app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, $inputs);

    $test = SusceptibilityTest::with('results.antibiotic')->sole();
    $byCode = $test->results->keyBy(fn ($r) => $r->antibiotic->code);
    expect($byCode['AMP']->automatic_interpretation)->toBe(Interpretation::Resistant)
        ->and($byCode['CIP']->automatic_interpretation)->toBe(Interpretation::Intermediate)
        ->and($byCode['GEN']->automatic_interpretation)->toBe(Interpretation::Susceptible)
        ->and($byCode['NIT']->automatic_interpretation)->toBe(Interpretation::Susceptible)
        ->and($byCode['NIT']->finalInterpretation())->toBe(Interpretation::Resistant)
        ->and($test->breakpointSet->is_synthetic)->toBeTrue();

    expect($isolate->fresh()->status)->toBe(IsolateStatus::Resolved)
        ->and($culture->fresh()->status)->toBe(CultureStatus::ClosedPositive)
        ->and(openActivity(ActivityType::ValidateReport)?->status)->toBe(ActivityStatus::Ready);

    $report = app(ValidateReport::class)->handle($this->biochemist, $culture->sampleStudy);
    $snapshot = $report->snapshot;
    expect($snapshot['result'])->toBe('Desarrollo')
        ->and($snapshot['isolates'])->toHaveCount(1)
        ->and($snapshot['isolates'][0]['organism'])->toBe('Escherichia coli')
        ->and($snapshot['isolates'][0]['count'])->toBe('≥ 10⁵ UFC/mL')
        ->and($snapshot['isolates'][0]['susceptibility']['breakpoints']['is_synthetic'])->toBeTrue()
        ->and(collect($snapshot['isolates'][0]['susceptibility']['results'])->firstWhere('antibiotic', 'Nitrofurantoína')['interpretation'])->toBe('R');

    expect(Activity::whereNotIn('status', ['completed', 'cancelled'])->count())->toBe(0)
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('reincubation schedules a new reading, only once', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();

    app(RecordReading::class)->handle($this->biochemist, $culture, 'REI', 'Colonias puntiformes');
    $next = Activity::where('type', ActivityType::ReadCulture->value)->where('attempt', 2)->sole();
    expect($next->status)->toBe(ActivityStatus::Scheduled);

    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null))
        ->toThrow(ValidationException::class);

    $this->travel(24)->hours();
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'REI', null))
        ->toThrow(ValidationException::class);

    app(RecordReading::class)->handle($this->biochemist, $culture, 'NEG', null);
    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedNegative);
});

test('growth requires at least one significant isolate', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();

    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, []))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, [new IsolateData('x', 'LT3', null, false)]))
        ->toThrow(ValidationException::class);
});

test('mixed flora closes the culture as contaminated', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();

    app(RecordReading::class)->handle($this->biochemist, $culture, 'MIX', null);

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedContaminated)
        ->and($culture->sampleStudy->fresh()->status)->toBe(SampleStudyStatus::ReadyForValidation);
});

test('an identification without susceptibility needs a reason and resolves the isolate', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();
    app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, [new IsolateData('Coco gram positivo', 'GE5', null, true)]);
    $isolate = Isolate::sole();
    $ssap = Organism::where('code', 'SSAP')->value('id');

    expect(fn () => app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, $ssap, IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, false, null))
        ->toThrow(ValidationException::class);

    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, $ssap, IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, false, 'No se informa antibiograma por protocolo');

    expect($isolate->fresh()->status)->toBe(IsolateStatus::Resolved)
        ->and($culture->fresh()->status)->toBe(CultureStatus::ClosedPositive)
        ->and(openActivity(ActivityType::AstSetup))->toBeNull();
});

test('susceptibility cannot be requested for a group without panel', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();
    app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, [new IsolateData('Levadura', 'GE5', null, true)]);

    expect(fn () => app(IdentifyIsolate::class)->handle($this->biochemist, Isolate::sole(), Organism::where('code', 'CALB')->value('id'), IdentificationMethod::Other, IdentificationLevel::Definitive, null, null, true, null))
        ->toThrow(ValidationException::class);
});

test('changing an automatic interpretation requires a reason', function () {
    $culture = receiveAndInoculate($this->biochemist);
    $this->travel(18)->hours();
    app(RecordReading::class)->handle($this->biochemist, $culture, 'POS', null, [new IsolateData('BGN', 'GE5', null, true)]);
    $isolate = Isolate::sole();
    app(IdentifyIsolate::class)->handle($this->biochemist, $isolate, Organism::where('code', 'ECOL')->value('id'), IdentificationMethod::Biochemical, IdentificationLevel::Definitive, null, null, true, null);
    $test = app(SetUpSusceptibilityTest::class)->handle($this->biochemist, $isolate, AstMethod::Disk);
    $this->travel(16)->hours();

    $inputs = $test->results->map(fn ($r, $i) => new ResultInput($r->antibiotic_id, null, 25.0, $i === 0 ? Interpretation::Resistant : null, null, true))->all();

    expect(fn () => app(RecordSusceptibilityResults::class)->handle($this->biochemist, $test, $inputs))
        ->toThrow(ValidationException::class);
});

test('the assistant cannot read cultures', function () {
    $assistant = userWithRole($this->site, Role::Assistant);
    $culture = receiveAndInoculate($assistant);
    $this->travel(18)->hours();
    setPermissionsTeamId($this->site->id);
    $assistant->unsetRelation('roles')->unsetRelation('permissions');

    expect(fn () => app(RecordReading::class)->handle($assistant, $culture, 'NEG', null))
        ->toThrow(AuthorizationException::class);
});
