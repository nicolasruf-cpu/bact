<?php

namespace App\Modules\Reporting;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Reporting\Enums\ReportAuditAction;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(ReportAuditAction::class);
    }
}
