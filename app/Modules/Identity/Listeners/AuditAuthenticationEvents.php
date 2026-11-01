<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Enums\IdentityAuditAction;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditAuthenticationEvents
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        DB::transaction(fn () => $this->auditor->record(
            IdentityAuditAction::LoggedIn,
            subject: $user,
            userId: $user?->getKey(),
        ));
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        DB::transaction(fn () => $this->auditor->record(
            IdentityAuditAction::LoggedOut,
            subject: $user,
            userId: $user?->getKey(),
        ));
    }

    public function handleFailed(Failed $event): void
    {
        // Nunca se registra la contraseña: solo el email intentado.
        DB::transaction(fn () => $this->auditor->record(
            IdentityAuditAction::LoginFailed,
            context: ['email' => $event->credentials['email'] ?? null],
        ));
    }
}
