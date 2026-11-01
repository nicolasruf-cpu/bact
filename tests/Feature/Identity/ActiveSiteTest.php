<?php

use App\Models\User;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\ActiveSite;
use App\Modules\Organization\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function userWithRoles(array $assignments): User
{
    $user = User::factory()->create();

    foreach ($assignments as [$site, $role]) {
        setPermissionsTeamId($site->id);
        $user->unsetRelation('roles')->assignRole($role->value);
    }

    setPermissionsTeamId(null);

    return $user->fresh();
}

test('the active site defaults to the first site where the user has a role', function () {
    [$norte, $central] = [Site::factory()->create(['name' => 'Norte']), Site::factory()->create(['name' => 'Central'])];
    Site::factory()->create(['name' => 'Sur']);
    $user = userWithRoles([[$norte, Role::Biochemist], [$central, Role::Assistant]]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.active.id', $central->id)
            ->has('site.available', 2));
});

test('permissions are evaluated in the active site', function () {
    [$central, $norte] = Site::factory()->count(2)->create();
    $user = userWithRoles([[$central, Role::Assistant], [$norte, Role::Biochemist]]);

    $activeSite = app(ActiveSite::class);

    $activeSite->switchTo($user, $central);
    expect($user->can(Permission::CulturesInoculate->value))->toBeTrue()
        ->and($user->can(Permission::CulturesReadPositive->value))->toBeFalse();

    $activeSite->switchTo($user, $norte);
    expect($user->can(Permission::CulturesReadPositive->value))->toBeTrue();
});

test('a user can switch to a site where they have a role', function () {
    [$central, $norte] = Site::factory()->count(2)->create();
    $user = userWithRoles([[$central, Role::Biochemist], [$norte, Role::Biochemist]]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('active-site.update'), ['site_id' => $norte->id])
        ->assertRedirect(route('dashboard'));

    expect(session(ActiveSite::SESSION_KEY))->toBe($norte->id);
});

test('a user cannot switch to a site where they have no role', function () {
    [$central, $sur] = Site::factory()->count(2)->create();
    $user = userWithRoles([[$central, Role::Biochemist]]);

    $this->actingAs($user)
        ->put(route('active-site.update'), ['site_id' => $sur->id])
        ->assertForbidden();
});

test('inactive sites are not available', function () {
    $inactive = Site::factory()->inactive()->create();
    $user = userWithRoles([[$inactive, Role::Biochemist]]);

    expect(app(ActiveSite::class)->availableFor($user))->toBeEmpty();
});

test('a user without roles has no active site', function () {
    Site::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.active', null)
            ->has('site.available', 0));
});

test('the assistant role matches the agreed matrix (Q-S1)', function () {
    $permissions = Role::Assistant->permissions();

    expect($permissions)
        ->toContain(Permission::SamplesReceive, Permission::CulturesInoculate, Permission::IdentificationBiochemSetup, Permission::IdentificationBiochemRead)
        ->not->toContain(Permission::CulturesReadNegative)
        ->not->toContain(Permission::CulturesReadPositive)
        ->not->toContain(Permission::IdentificationPerform)
        ->not->toContain(Permission::ReportsValidate);
});

test('the administrator has no clinical permissions', function () {
    expect(Role::Admin->permissions())
        ->not->toContain(Permission::CulturesInoculate)
        ->not->toContain(Permission::ReportsValidate)
        ->not->toContain(Permission::SamplesReceive);
});
