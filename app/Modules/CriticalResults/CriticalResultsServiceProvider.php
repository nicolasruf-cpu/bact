<?php

namespace App\Modules\CriticalResults;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\BloodCultures\Events\GramStainRecorded;
use App\Modules\CriticalResults\Enums\CriticalAuditAction;
use App\Modules\CriticalResults\Listeners\OpenAlertOnPositiveGram;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CriticalResultsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(CriticalAuditAction::class);

        Event::listen(GramStainRecorded::class, OpenAlertOnPositiveGram::class);
    }
}
