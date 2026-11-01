<?php

namespace App\Modules\Identity;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Identity\Enums\IdentityAuditAction;
use App\Modules\Identity\Listeners\AuditAuthenticationEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(IdentityAuditAction::class);

        Event::listen(Login::class, [AuditAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [AuditAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [AuditAuthenticationEvents::class, 'handleFailed']);
    }
}
