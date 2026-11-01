<?php

namespace App\Modules\Audit\Console;

use App\Modules\Audit\Services\AuditChainVerifier;
use Illuminate\Console\Command;

class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verifica la integridad de la cadena de auditoría';

    public function handle(AuditChainVerifier $verifier): int
    {
        $report = $verifier->verify();

        if ($report->valid) {
            $this->components->info("Cadena de auditoría íntegra ({$report->checked} eventos verificados).");

            return self::SUCCESS;
        }

        $this->components->error("Cadena de auditoría ROTA en el evento #{$report->brokenAtEventId}: {$report->problem}");

        return self::FAILURE;
    }
}
