<?php

namespace App\Modules\Cultures;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Cultures\Enums\CultureAuditAction;
use Illuminate\Support\ServiceProvider;

class CulturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(CultureAuditAction::class);
    }
}
