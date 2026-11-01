<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Actions\ValidateReport;
use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Services\ReportBuilder;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValidationController extends Controller
{
    public function create(Request $request, SampleStudy $sampleStudy, SampleHeader $header, ReportBuilder $builder): Response
    {
        $this->authorizeFor($request, $sampleStudy);

        return Inertia::render('reporting/Validate', [
            'sample' => $header->for($sampleStudy),
            'status' => $sampleStudy->status->value,
            'preview' => $builder->build($sampleStudy),
        ]);
    }

    public function store(Request $request, SampleStudy $sampleStudy, ValidateReport $validate): RedirectResponse
    {
        $this->authorizeFor($request, $sampleStudy);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirmá que revisaste el informe.']);

        /** @var User $user */
        $user = $request->user();
        $report = $validate->handle($user, $sampleStudy);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Informe validado y emitido.']);

        return to_route('reports.show', $report);
    }

    public function show(Request $request, Report $report): Response
    {
        $study = $report->sampleStudy;
        abort_unless($request->user()?->can(Permission::SamplesView->value), 403);
        // El informe lo ve la sede de origen de la muestra y la que lo procesó (ADR-0008).
        abort_unless(in_array(getPermissionsTeamId(), [$study->processing_site_id, $study->sample->origin_site_id], true), 403);

        return Inertia::render('reporting/Report', [
            'report' => [
                'ulid' => $report->ulid,
                'version' => $report->version,
                'type' => $report->type->label(),
                'status' => $report->status->label(),
                'validated_at' => $report->validated_at->toIso8601String(),
                'validated_by' => $report->validator->name,
                'content' => $report->snapshot,
            ],
        ]);
    }

    private function authorizeFor(Request $request, SampleStudy $study): void
    {
        abort_unless($request->user()?->can(Permission::ReportsValidate->value), 403);
        abort_unless($study->processing_site_id === getPermissionsTeamId(), 403);
    }
}
