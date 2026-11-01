<?php

namespace App\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Organization\Models\Site;
use App\Modules\Workflow\Queries\InoculationWorkbenchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla principal: contadores de las mesadas de la sede activa.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, InoculationWorkbenchQuery $inoculation): Response
    {
        $site = $request->attributes->get('activeSite');
        $user = $request->user();

        $counters = [];
        if ($site instanceof Site && $user?->can(Permission::CulturesInoculate->value)) {
            $counters['inoculation'] = $inoculation->counters($site->id);
        }

        return Inertia::render('Dashboard', ['counters' => $counters]);
    }
}
