<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Patients\PatientsServiceProvider;
use App\Modules\Samples\SamplesServiceProvider;
use App\Modules\Workflow\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    PatientsServiceProvider::class,
    SamplesServiceProvider::class,
    WorkflowServiceProvider::class,
];
