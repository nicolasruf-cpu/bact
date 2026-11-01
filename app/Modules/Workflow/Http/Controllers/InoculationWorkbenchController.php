<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Site;
use App\Modules\Workflow\Queries\InoculationWorkbenchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InoculationWorkbenchController extends Controller
{
    public function index(Request $request, InoculationWorkbenchQuery $query): Response
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        return Inertia::render('workflow/InoculationWorkbench', [
            'items' => $query->items($site->id),
            'counters' => $query->counters($site->id),
        ]);
    }
}
