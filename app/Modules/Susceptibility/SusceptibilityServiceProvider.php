<?php

namespace App\Modules\Susceptibility;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use Illuminate\Support\ServiceProvider;

class SusceptibilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(SusceptibilityAuditAction::class);
    }
}
