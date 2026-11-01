<?php

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Organism;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->site = Site::factory()->create();
    $this->admin = userWithRole($this->site, Role::Admin);
});

test('el Administrador agrega un medio y el código queda en mayúsculas', function () {
    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'media'), [
        'code' => 'cled', 'name' => 'Agar CLED (sintético)', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(Medium::where('code', 'CLED')->value('name'))->toBe('Agar CLED (sintético)');
    expect(AuditEvent::where('action', 'configuration.catalog_entry_created')->count())->toBe(1);

    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'media'), [
        'code' => 'CLED', 'name' => 'Repetido', 'is_active' => true,
    ])->assertSessionHasErrors('code');
});

test('se cambia el nombre o se desactiva, pero el código no', function () {
    $medium = Medium::create(['code' => 'XX', 'name' => 'Medio X']);

    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'media'), [
        'id' => $medium->id, 'code' => 'YY', 'name' => 'Medio X renombrado', 'is_active' => false, 'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    $medium->refresh();
    expect($medium->code)->toBe('XX')
        ->and($medium->name)->toBe('Medio X renombrado')
        ->and($medium->is_active)->toBeFalse();

    // Con la versión vieja, se rechaza.
    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'media'), [
        'id' => $medium->id, 'name' => 'Otro', 'is_active' => true, 'lock_version' => 0,
    ])->assertSessionHasErrors('lock_version');
});

test('un microorganismo nuevo necesita su grupo', function () {
    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'organisms'), [
        'code' => 'ORG-SINT', 'name' => 'Organismo sintético', 'is_active' => true,
    ])->assertSessionHasErrors('organism_group');

    $this->actingAs($this->admin)->post(route('admin.catalogs.store', 'organisms'), [
        'code' => 'ORG-SINT', 'name' => 'Organismo sintético', 'is_active' => true, 'organism_group' => 'enterobacterales',
    ])->assertSessionHasNoErrors();

    expect(Organism::where('code', 'ORG-SINT')->first()->organism_group)->toBe(OrganismGroup::Enterobacterales);
});

test('el Bioquímico no edita catálogos y un catálogo desconocido no existe', function () {
    $biochemist = userWithRole($this->site, Role::Biochemist);

    $this->actingAs($biochemist)->get(route('admin.catalogs.index', 'media'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.catalogs.index', 'nada'))->assertNotFound();
    $this->actingAs($this->admin)->get(route('admin.catalogs.index', 'sites'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/catalogs/Index')->has('entries', 1));
});
