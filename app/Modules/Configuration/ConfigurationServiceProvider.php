<?php

namespace App\Modules\Configuration;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use Illuminate\Support\ServiceProvider;

class ConfigurationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(ConfigurationAuditAction::class);
    }
}
