<?php

namespace App\Modules\BloodCultures;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\BloodCultures\Enums\BloodCultureAuditAction;
use Illuminate\Support\ServiceProvider;

class BloodCulturesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(BloodCultureAuditAction::class);
    }
}
