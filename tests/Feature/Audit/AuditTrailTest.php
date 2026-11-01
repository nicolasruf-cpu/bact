<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function audit(IdentityAuditAction $action = IdentityAuditAction::LoggedIn, array $after = []): AuditEvent
{
    return DB::transaction(fn () => app(Auditor::class)->record($action, after: $after));
}

test('events are chained by hash', function () {
    $first = audit();
    $second = audit();

    expect($first->previous_hash)->toBeNull()
        ->and($second->previous_hash)->toBe($first->hash)
        ->and($second->hash)->not->toBe($first->hash);

    expect(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('auditing outside a transaction is rejected', function () {
    app(Auditor::class)->record(IdentityAuditAction::LoggedIn);
})->throws(LogicException::class);

test('a rolled back operation leaves no audit event', function () {
    audit();

    try {
        DB::transaction(function () {
            app(Auditor::class)->record(IdentityAuditAction::LoggedIn);
            throw new RuntimeException('falla la operación clínica');
        });
    } catch (RuntimeException) {
    }

    expect(AuditEvent::count())->toBe(1)
        ->and(app(AuditChainVerifier::class)->verify()->valid)->toBeTrue();
});

test('the database rejects updates of audit events', function () {
    $event = audit();

    DB::table('audit_events')->where('id', $event->id)->update(['reason' => 'alterado']);
})->throws(QueryException::class, 'append-only');

test('the database rejects deletion of audit events', function () {
    $event = audit();

    DB::table('audit_events')->where('id', $event->id)->delete();
})->throws(QueryException::class, 'append-only');

test('the model refuses to modify an event', function () {
    $event = audit();
    $event->reason = 'alterado';
    $event->save();
})->throws(LogicException::class);

test('the verifier detects an event whose content does not match its hash', function () {
    $last = audit();

    // Simula una fila alterada: apunta bien al anterior pero su contenido no coincide con el hash.
    $forgedHash = str_repeat('a', 64);
    DB::table('audit_events')->insert([
        'occurred_at' => now()->format('Y-m-d H:i:s.u'),
        'action' => 'identity.logged_in',
        'previous_hash' => $last->hash,
        'hash' => $forgedHash,
    ]);
    DB::table('audit_chain_head')->update(['last_hash' => $forgedHash]);

    $report = app(AuditChainVerifier::class)->verify();

    expect($report->valid)->toBeFalse()
        ->and($report->problem)->toContain('modificado');
});

test('the verifier detects a broken link', function () {
    audit();

    DB::table('audit_events')->insert([
        'occurred_at' => now()->format('Y-m-d H:i:s.u'),
        'action' => 'identity.logged_in',
        'previous_hash' => str_repeat('b', 64),
        'hash' => str_repeat('c', 64),
    ]);

    expect(app(AuditChainVerifier::class)->verify()->problem)->toContain('anterior');
});

test('the verifier detects events removed from the end', function () {
    audit();
    DB::table('audit_chain_head')->update(['last_hash' => str_repeat('d', 64)]);

    expect(app(AuditChainVerifier::class)->verify()->problem)->toContain('cabeza');
});

test('the audit:verify command reports the result', function () {
    audit();

    $this->artisan('audit:verify')->assertSuccessful();

    DB::table('audit_chain_head')->update(['last_hash' => str_repeat('d', 64)]);

    $this->artisan('audit:verify')->assertFailed();
});

test('before, after and context are stored', function () {
    $event = DB::transaction(fn () => app(Auditor::class)->record(
        IdentityAuditAction::ActiveSiteSwitched,
        before: ['site_id' => 1],
        after: ['site_id' => 2],
        reason: 'motivo de prueba',
        context: ['batch_id' => 7],
    ));

    expect($event->fresh())
        ->before->toBe(['site_id' => 1])
        ->after->toBe(['site_id' => 2])
        ->context->toBe(['batch_id' => 7])
        ->reason->toBe('motivo de prueba');
});

test('a successful login is audited', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $event = AuditEvent::where('action', IdentityAuditAction::LoggedIn->value)->sole();
    expect($event->user_id)->toBe($user->id)
        ->and($event->request_id)->not->toBeNull();
});

test('a failed login is audited without the password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrecta']);

    $event = AuditEvent::where('action', IdentityAuditAction::LoginFailed->value)->sole();
    expect($event->context)->toBe(['email' => $user->email])
        ->and(json_encode($event->getAttributes()))->not->toContain('incorrecta');
});

test('switching the active site is audited', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    [$central, $norte] = Site::factory()->count(2)->create();
    $user = User::factory()->create();
    foreach ([$central, $norte] as $site) {
        setPermissionsTeamId($site->id);
        $user->unsetRelation('roles')->assignRole(Role::Biochemist->value);
    }

    $this->actingAs($user)->put(route('active-site.update'), ['site_id' => $norte->id]);

    $event = AuditEvent::where('action', IdentityAuditAction::ActiveSiteSwitched->value)->sole();
    expect($event->after)->toBe(['site_id' => $norte->id])
        ->and($event->user_id)->toBe($user->id);
});

test('the audit log is visible only with audit.view in the active site', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $site = Site::factory()->create();
    $director = User::factory()->create();
    $biochemist = User::factory()->create();
    setPermissionsTeamId($site->id);
    $director->assignRole(Role::TechnicalDirector->value);
    $biochemist->assignRole(Role::Biochemist->value);
    setPermissionsTeamId(null);

    $this->actingAs($director)->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('audit/Index')->has('events.data'));

    $this->actingAs($biochemist)->get(route('audit.index'))->assertForbidden();
});
