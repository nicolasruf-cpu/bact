<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Queries\AuditLogQuery;
use App\Modules\Audit\Services\AuditActionRegistry;
use App\Modules\Audit\Services\AuditChainVerifier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        AuditLogQuery $query,
        AuditActionRegistry $registry,
        AuditChainVerifier $verifier,
    ): Response {
        $action = $request->string('action')->toString() ?: null;
        $activeSite = getPermissionsTeamId();

        return Inertia::render('audit/Index', [
            'events' => $query->paginate(is_numeric($activeSite) ? (int) $activeSite : null, $action),
            'actions' => $registry->options(),
            'filters' => ['action' => $action],
            'chain' => Inertia::defer(function () use ($verifier) {
                $report = $verifier->verify();

                return ['valid' => $report->valid, 'checked' => $report->checked, 'problem' => $report->problem];
            }),
        ]);
    }
}
