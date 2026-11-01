<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\BloodCultures\BloodCulturesServiceProvider;
use App\Modules\Configuration\ConfigurationServiceProvider;
use App\Modules\CriticalResults\CriticalResultsServiceProvider;
use App\Modules\Cultures\CulturesServiceProvider;
use App\Modules\Identification\IdentificationServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Patients\PatientsServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Samples\SamplesServiceProvider;
use App\Modules\Susceptibility\SusceptibilityServiceProvider;
use App\Modules\WorkBatches\WorkBatchesServiceProvider;
use App\Modules\Workflow\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    ConfigurationServiceProvider::class,
    PatientsServiceProvider::class,
    OrdersServiceProvider::class,
    SamplesServiceProvider::class,
    CulturesServiceProvider::class,
    BloodCulturesServiceProvider::class,
    CriticalResultsServiceProvider::class,
    IdentificationServiceProvider::class,
    SusceptibilityServiceProvider::class,
    ReportingServiceProvider::class,
    WorkflowServiceProvider::class,
    WorkBatchesServiceProvider::class,
];
