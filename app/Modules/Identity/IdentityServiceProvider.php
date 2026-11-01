<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Listeners\AuditAuthenticationEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(IdentityAuditAction::class);

        Event::listen(Login::class, [AuditAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [AuditAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [AuditAuthenticationEvents::class, 'handleFailed']);

        // Habilidad global (no por sede): solo el Director técnico designado (Q-A8).
        Gate::define(Permission::CLINICAL_APPROVE, fn (User $user) => $user->is_active && $user->is_clinical_approver);
        Gate::define(Permission::CLINICAL_VIEW, fn (User $user) => $user->can(Permission::ConfigClinicalDraft->value) || $user->can(Permission::CLINICAL_APPROVE));
    }
}
