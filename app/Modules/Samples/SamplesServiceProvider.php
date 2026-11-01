<?php

namespace App\Modules\Samples;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Samples\Enums\SampleAuditAction;
use Illuminate\Support\ServiceProvider;

class SamplesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(SampleAuditAction::class);
    }
}
