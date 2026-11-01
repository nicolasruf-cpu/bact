<?php

namespace App\Modules\Patients;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Patients\Enums\PatientAuditAction;
use Illuminate\Support\ServiceProvider;

class PatientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(PatientAuditAction::class);
    }
}
