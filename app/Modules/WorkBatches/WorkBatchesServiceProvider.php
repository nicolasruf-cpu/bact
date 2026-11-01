<?php

namespace App\Modules\WorkBatches;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\WorkBatches\Enums\WorkBatchAuditAction;
use App\Modules\WorkBatches\Listeners\MarkBatchItemDone;
use App\Modules\WorkBatches\Models\WorkBatch;
use App\Modules\WorkBatches\Policies\WorkBatchPolicy;
use App\Modules\Workflow\Events\ActivityCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WorkBatchesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(WorkBatchAuditAction::class);

        Gate::policy(WorkBatch::class, WorkBatchPolicy::class);

        Event::listen(ActivityCompleted::class, MarkBatchItemDone::class);
    }
}
