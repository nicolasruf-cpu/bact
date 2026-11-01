<?php

namespace App\Modules\Identification;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Identification\Enums\IdentificationAuditAction;
use Illuminate\Support\ServiceProvider;

class IdentificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(IdentificationAuditAction::class);
    }
}
