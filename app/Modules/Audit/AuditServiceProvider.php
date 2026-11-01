<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Console\VerifyAuditChain;
use App\Modules\Audit\Services\AuditActionRegistry;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditActionRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([VerifyAuditChain::class]);
        }
    }
}
