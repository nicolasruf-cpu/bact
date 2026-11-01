<?php

namespace App\Modules\Orders;

use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Orders\Enums\OrderAuditAction;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuditActionRegistry::class)->register(OrderAuditAction::class);
    }
}
