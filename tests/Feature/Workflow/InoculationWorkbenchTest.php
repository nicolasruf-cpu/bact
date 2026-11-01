<?php

use App\Models\User;
use App\Modules\Configuration\Database\Seeders\UrineCultureTemplateSeeder;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(UrineCultureTemplateSeeder::class)->run(ProtocolVersionStatus::Active);
});

function memberOf(Site $site, Role $role): User
{
    $user = User::factory()->create();
    setPermissionsTeamId($site->id);
    $user->unsetRelation('roles')->assignRole($role->value);
    setPermissionsTeamId(null);

    return $user;
}

function receive(User $user, string $barcode, string $priority = 'routine', string $sampleType = 'URI-CM'): void
{
    test()->actingAs($user)->post(route('reception.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'barcode' => $barcode,
        'document_number' => fake()->unique()->numerify('########'),
        'last_name' => 'Sintético',
        'first_name' => 'Paciente',
        'sex' => 'U',
        'sample_type_id' => SampleType::where('code', $sampleType)->value('id'),
        'study_ids' => [Study::where('code', 'URO')->value('id')],
        'priority' => $priority,
    ])->assertSessionHasNoErrors();
}

test('the inoculation workbench shows pending inoculations of the active site, urgent first', function () {
    $site = Site::factory()->create();
    $user = memberOf($site, Role::Assistant);

    receive($user, 'M-1');
    receive($user, 'M-2', 'urgent', 'URI-SV');

    $this->actingAs($user)->get(route('workbenches.inoculation'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workflow/InoculationWorkbench')
            ->has('items', 2)
            ->where('items.0.barcode', 'M-2')
            ->where('items.0.priority', 'urgent')
            ->where('items.0.media.0.code', 'AS')
            ->where('items.0.media.0.inoculum', 'Asa calibrada 10 µL')
            ->where('items.1.barcode', 'M-1')
            ->where('counters', ['ready' => 2, 'in_progress' => 0, 'urgent' => 1]));
});

test('each site only sees its own inoculations', function () {
    [$central, $norte] = Site::factory()->count(2)->create();
    receive(memberOf($central, Role::Assistant), 'M-CEN');
    $norteUser = memberOf($norte, Role::Assistant);
    receive($norteUser, 'M-NOR');

    $this->actingAs($norteUser)->get(route('workbenches.inoculation'))
        ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.barcode', 'M-NOR'));
});

test('the inoculation workbench requires cultures.inoculate', function () {
    $site = Site::factory()->create();

    $this->actingAs(memberOf($site, Role::Viewer))->get(route('workbenches.inoculation'))->assertForbidden();
    $this->actingAs(memberOf($site, Role::Admin))->get(route('workbenches.inoculation'))->assertForbidden();
});

test('the dashboard shows the inoculation counters', function () {
    $site = Site::factory()->create();
    $user = memberOf($site, Role::Assistant);
    receive($user, 'M-1', 'stat');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('counters.inoculation.ready', 1)->where('counters.inoculation.urgent', 1));
});
