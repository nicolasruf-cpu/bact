<?php

use App\Modules\Configuration\Database\Seeders\ReferenceCatalogSeeder;
use App\Modules\Configuration\Database\Seeders\SyntheticBreakpointSeeder;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use App\Modules\Reporting\Models\Report;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
    $this->seed(ReferenceCatalogSeeder::class);
    $this->seed(SyntheticBreakpointSeeder::class);
    $this->site = Site::factory()->create();
    $this->assistant = userWithRole($this->site, Role::Assistant);
    $this->biochemist = userWithRole($this->site, Role::Biochemist);
});

test('urine culture end to end through the screens, up to the susceptibility profile', function () {
    // Recepción y siembra (Auxiliar).
    receiveUrineOrder($this->assistant, ['M-100']);
    $study = SampleStudy::sole();
    $this->actingAs($this->assistant)->post(route('inoculations.store', $study))->assertSessionHasNoErrors();
    $culture = Culture::sole();

    // La lectura todavía no está disponible.
    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'reading'))
        ->assertInertia(fn (Assert $page) => $page->component('workflow/Workbench')->has('items', 0)->where('counters.scheduled', 1));

    $this->travel(18)->hours();

    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'reading'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.barcode', 'M-100'));
    $this->actingAs($this->biochemist)->get(route('readings.create', $culture))
        ->assertInertia(fn (Assert $page) => $page->component('cultures/Reading')->has('options', 5)->has('countCategories', 4)->where('sample.barcode', 'M-100'));

    $this->actingAs($this->biochemist)->post(route('readings.store', $culture), [
        'option_code' => 'POS',
        'isolates' => [['morphotype' => 'BGN lactosa +', 'count_category' => 'GE5', 'plate_id' => $culture->plates->first()->id, 'is_significant' => true]],
    ])->assertRedirect(route('workbenches.show', 'reading'))->assertSessionHasNoErrors();

    // Identificación.
    $isolate = Isolate::sole();
    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'identification'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1));
    $this->actingAs($this->biochemist)->get(route('identifications.create', $isolate))
        ->assertInertia(fn (Assert $page) => $page->component('identification/Identify')->where('isolate.number', 1));
    $this->actingAs($this->biochemist)->post(route('identifications.store', $isolate), [
        'organism_id' => Organism::where('code', 'ECOL')->value('id'),
        'method' => 'biochemical',
        'level' => 'definitive',
        'tests' => 'TSI A/A',
        'requires_susceptibility' => true,
    ])->assertSessionHasNoErrors();

    // Antibiograma: preparación y lectura.
    $this->actingAs($this->biochemist)->get(route('susceptibility.show', $isolate))
        ->assertInertia(fn (Assert $page) => $page->component('susceptibility/Susceptibility')->where('test', null)->has('panels', 2)->where('panels.0.code', 'URO-ENT')->where('breakpointSet.is_synthetic', true));
    $this->actingAs($this->biochemist)->post(route('susceptibility.setup', $isolate), ['method' => 'disk'])->assertSessionHasNoErrors();

    $test = SusceptibilityTest::with('results')->sole();
    $this->travel(16)->hours();
    $this->actingAs($this->biochemist)->get(route('susceptibility.show', $isolate))
        ->assertInertia(fn (Assert $page) => $page->where('test.status', 'set_up')->has('test.results', 14)->has('test.breakpoints'));

    $this->actingAs($this->biochemist)->post(route('susceptibility.read', $isolate), [
        'results' => $test->results->map(fn ($r, $i) => [
            'antibiotic_id' => $r->antibiotic_id,
            'value' => $i === 0 ? 10 : 22, // ficticio: el primero R, el resto S
            'is_reported' => true,
        ])->all(),
    ])->assertSessionHasNoErrors();

    // Validación e informe.
    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'validation'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1));
    $this->actingAs($this->biochemist)->get(route('validations.create', $study))
        ->assertInertia(fn (Assert $page) => $page->component('reporting/Validate')->where('preview.isolates.0.organism', 'Escherichia coli'));

    $this->actingAs($this->biochemist)->post(route('validations.store', $study))->assertSessionHasErrors('confirm');
    $this->actingAs($this->biochemist)->post(route('validations.store', $study), ['confirm' => true])->assertSessionHasNoErrors();

    $report = Report::sole();
    $this->actingAs($this->biochemist)->get(route('reports.show', $report))
        ->assertInertia(fn (Assert $page) => $page->component('reporting/Report')->where('report.content.isolates.0.susceptibility.results.0.interpretation', 'R'));

    // Perfil de sensibilidad.
    $this->actingAs($this->biochemist)->get(route('statistics.susceptibility'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('statistics/SusceptibilityProfile')
            ->where('total_isolates', 1)
            ->where('organisms.0.organism', 'Escherichia coli')
            ->where('organisms.0.insufficient', true)
            ->has('organisms.0.antibiotics', 14));

    // Inicio: todas las mesadas vacías.
    $this->actingAs($this->biochemist)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('counters.validation.ready', 0)->where('counters.reading.ready', 0));
});

test('the susceptibility profile counts the first isolate per patient and species', function () {
    // Dos urocultivos del mismo paciente con E. coli: cuenta uno solo.
    foreach (['M-1', 'M-2'] as $barcode) {
        $this->actingAs($this->assistant)->post(route('reception.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'document_number' => '30111222', 'last_name' => 'Sintético', 'first_name' => 'Paciente', 'sex' => 'U', 'priority' => 'routine',
            'samples' => [['barcode' => $barcode, 'sample_type_id' => SampleType::where('code', 'URI-CM')->value('id'), 'study_ids' => [Study::where('code', 'URO')->value('id')]]],
        ])->assertSessionHasNoErrors();
    }

    foreach (SampleStudy::all() as $study) {
        $this->actingAs($this->assistant)->post(route('inoculations.store', $study));
    }
    $this->travel(18)->hours();
    foreach (Culture::all() as $culture) {
        $this->actingAs($this->biochemist)->post(route('readings.store', $culture), [
            'option_code' => 'POS',
            'isolates' => [['morphotype' => 'BGN', 'count_category' => 'GE5', 'is_significant' => true]],
        ]);
    }
    foreach (Isolate::all() as $isolate) {
        $this->actingAs($this->biochemist)->post(route('identifications.store', $isolate), [
            'organism_id' => Organism::where('code', 'ECOL')->value('id'), 'method' => 'biochemical', 'level' => 'definitive', 'requires_susceptibility' => true,
        ]);
        $this->actingAs($this->biochemist)->post(route('susceptibility.setup', $isolate), ['method' => 'disk']);
    }
    $this->travel(16)->hours();
    foreach (SusceptibilityTest::with('results')->get() as $test) {
        $this->actingAs($this->biochemist)->post(route('susceptibility.read', $test->isolate), [
            'results' => $test->results->map(fn ($r) => ['antibiotic_id' => $r->antibiotic_id, 'value' => 25, 'is_reported' => true])->all(),
        ])->assertSessionHasNoErrors();
    }
    foreach (SampleStudy::all() as $study) {
        $this->actingAs($this->biochemist)->post(route('validations.store', $study), ['confirm' => true])->assertSessionHasNoErrors();
    }

    $this->actingAs($this->biochemist)->get(route('statistics.susceptibility'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total_isolates', 1)
            ->where('organisms.0.antibiotics.0.tested', 1)
            ->where('organisms.0.antibiotics.0.percent_susceptible', 100));
});

test('screens of another site are not accessible', function () {
    receiveUrineOrder($this->assistant, ['M-1']);
    $this->actingAs($this->assistant)->post(route('inoculations.store', SampleStudy::sole()));
    $other = userWithRole(Site::factory()->create(), Role::Biochemist);

    $this->actingAs($other)->get(route('readings.create', Culture::sole()))->assertForbidden();
});

test('workbenches require their permission', function () {
    $this->actingAs($this->assistant)->get(route('workbenches.show', 'reading'))->assertForbidden();
    $this->actingAs($this->assistant)->get(route('workbenches.show', 'validation'))->assertForbidden();
    $this->actingAs($this->assistant)->get(route('statistics.susceptibility'))->assertForbidden();
    $this->actingAs($this->biochemist)->get(route('workbenches.show', 'reading'))->assertOk();
});

test('the reading screen sends the early confirmation and its reason', function () {
    receiveUrineOrder($this->assistant, ['M-200']);
    $this->actingAs($this->assistant)->post(route('inoculations.store', SampleStudy::sole()))->assertSessionHasNoErrors();
    $culture = Culture::sole();

    $this->actingAs($this->biochemist)->post(route('readings.store', $culture), ['option_code' => 'NEG'])
        ->assertSessionHasErrors('early_confirmed');
    $this->actingAs($this->biochemist)->post(route('readings.store', $culture), [
        'option_code' => 'NEG',
        'early_confirmed' => true,
        'early_reason' => 'Se leyó junto con la tanda de la mañana.',
    ])->assertSessionHasNoErrors();

    expect($culture->fresh()->status)->toBe(CultureStatus::ClosedNegative);
});
