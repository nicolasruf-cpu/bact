<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Queries\MarchQuery;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marcha de un estudio: cómo se llegó al resultado, paso a paso, solo con lo registrado.
 */
class MarchController extends Controller
{
    public function show(Request $request, SampleStudy $sampleStudy, MarchQuery $march, SampleHeader $header): Response
    {
        abort_unless($request->user()?->can(Permission::SamplesView->value), 403);
        abort_unless($sampleStudy->processing_site_id === getPermissionsTeamId(), 403);

        return Inertia::render('reporting/March', [
            'sample' => $header->for($sampleStudy),
            ...$march->forStudy($sampleStudy),
        ]);
    }
}
