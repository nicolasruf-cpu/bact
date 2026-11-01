<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\SiteRoles;
use App\Modules\Organization\Models\Site;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->central = Site::factory()->create(['code' => 'CEN', 'name' => 'Central']);
    $this->norte = Site::factory()->create(['code' => 'NOR', 'name' => 'Norte']);
    $this->admin = userWithRole($this->central, Role::Admin);
});

function assignmentsOf(User $user): array
{
    return array_map(fn (array $a) => [$a['site_id'], $a['role']], app(SiteRoles::class)->assignmentsOf($user));
}

test('el Administrador da de alta un usuario con perfiles en sus sedes y queda auditado', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Bea Sintética',
        'email' => 'Bea@Ejemplo.test',
        'password' => 'clave-inicial-1',
        'assignments' => [['site_id' => $this->central->id, 'role' => Role::Biochemist->value]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'bea@ejemplo.test')->firstOrFail();
    expect($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(assignmentsOf($user))->toBe([[$this->central->id, Role::Biochemist]]);

    $event = AuditEvent::where('action', 'identity.user_created')->firstOrFail();
    expect($event->subject_id)->toBe($user->id)
        ->and(json_encode($event->after))->not->toContain('clave-inicial-1');
    expect(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();

    // Puede ingresar con la contraseña inicial.
    auth()->logout();
    $this->post(route('login.store'), ['email' => 'bea@ejemplo.test', 'password' => 'clave-inicial-1'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

test('no se asignan perfiles en sedes donde el Administrador no administra usuarios', function () {
    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Otro',
        'email' => 'otro@ejemplo.test',
        'password' => 'clave-inicial-1',
        'assignments' => [['site_id' => $this->norte->id, 'role' => Role::Biochemist->value]],
    ])->assertSessionHasErrors('assignments');

    expect(User::where('email', 'otro@ejemplo.test')->exists())->toBeFalse();
});

test('al modificar, los perfiles de otras sedes quedan como estaban', function () {
    $user = userWithRole($this->central, Role::Assistant);
    setPermissionsTeamId($this->norte->id);
    $user->unsetRelation('roles')->assignRole(Role::Biochemist->value);
    setPermissionsTeamId(null);

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'password' => null,
        'assignments' => [['site_id' => $this->central->id, 'role' => Role::Biochemist->value]],
        'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    expect(assignmentsOf($user))->toEqualCanonicalizing([
        [$this->central->id, Role::Biochemist],
        [$this->norte->id, Role::Biochemist],
    ]);
    expect(AuditEvent::where('action', 'identity.user_roles_changed')->count())->toBe(1);
});

test('nadie cambia sus propios perfiles ni se da de baja a sí mismo', function () {
    $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'assignments' => [],
        'lock_version' => 0,
    ])->assertSessionHasErrors('assignments');

    $this->actingAs($this->admin)->post(route('admin.users.active', $this->admin), [
        'active' => false, 'reason' => 'prueba', 'lock_version' => 0,
    ])->assertSessionHasErrors('reason');

    expect(assignmentsOf($this->admin->fresh()))->toBe([[$this->central->id, Role::Admin]]);
});

test('una modificación con una versión vieja se rechaza', function () {
    $user = userWithRole($this->central, Role::Assistant);
    $user->forceFill(['lock_version' => 3])->save();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'Cambiado',
        'email' => $user->email,
        'assignments' => [['site_id' => $this->central->id, 'role' => Role::Assistant->value]],
        'lock_version' => 2,
    ])->assertSessionHasErrors('lock_version');

    expect($user->fresh()->name)->not->toBe('Cambiado');
});

test('un usuario dado de baja no ingresa y pierde la sesión abierta', function () {
    $user = userWithRole($this->central, Role::Biochemist);

    $this->actingAs($this->admin)->post(route('admin.users.active', $user), [
        'active' => false, 'reason' => 'Dejó el laboratorio', 'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->is_active)->toBeFalse();
    expect(AuditEvent::where('action', 'identity.user_deactivated')->value('reason'))->toBe('Dejó el laboratorio');

    // Sesión abierta: el próximo pedido la corta.
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();

    // Ingreso con la contraseña correcta: bloqueado y auditado.
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(AuditEvent::where('action', 'identity.inactive_user_blocked')->count())->toBe(1);
});

test('el responsable de la configuración clínica es un único Director técnico', function () {
    $dt1 = userWithRole($this->central, Role::TechnicalDirector);
    $dt2 = userWithRole($this->norte, Role::TechnicalDirector);
    $biochemist = userWithRole($this->central, Role::Biochemist);

    $this->actingAs($this->admin)->post(route('admin.clinical-approver.update'), ['user_id' => $biochemist->id, 'reason' => 'x'])
        ->assertSessionHasErrors('user_id');

    $this->actingAs($this->admin)->post(route('admin.clinical-approver.update'), ['user_id' => $dt1->id, 'reason' => 'Designación inicial'])
        ->assertSessionHasNoErrors();
    expect(Gate::forUser($dt1->fresh())->allows(Permission::CLINICAL_APPROVE))->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.clinical-approver.update'), ['user_id' => $dt2->id, 'reason' => 'Cambio de responsable'])
        ->assertSessionHasNoErrors();

    expect($dt1->fresh()->is_clinical_approver)->toBeFalse()
        ->and($dt2->fresh()->is_clinical_approver)->toBeTrue()
        ->and(User::where('is_clinical_approver', true)->count())->toBe(1)
        ->and(Gate::forUser($dt1->fresh())->allows(Permission::CLINICAL_APPROVE))->toBeFalse();

    // La baja del responsable le quita la designación.
    $this->actingAs($this->admin)->post(route('admin.users.active', $dt2), [
        'active' => false, 'reason' => 'Licencia', 'lock_version' => $dt2->fresh()->lock_version,
    ])->assertSessionHasNoErrors();
    expect(User::where('is_clinical_approver', true)->count())->toBe(0);
    expect(AuditEvent::where('action', 'identity.clinical_approver_designated')->count())->toBe(3);
});

test('quien no administra usuarios no entra a la pantalla', function () {
    $biochemist = userWithRole($this->central, Role::Biochemist);

    $this->actingAs($biochemist)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/Index')->has('users', 2));
});

test('las pantallas de alta y edición abren', function () {
    $user = userWithRole($this->central, Role::Biochemist);

    $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/Edit')
            ->where('user.assignments.0.role', Role::Biochemist->value)
            ->has('sites', 2));
});
