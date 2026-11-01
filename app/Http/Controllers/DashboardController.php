<?php

namespace App\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Organization\Models\Site;
use App\Modules\Workflow\Enums\Workbench;
use App\Modules\Workflow\Http\Controllers\BloodCultureWorkbenchController;
use App\Modules\Workflow\Queries\BloodCultureWorkbenchQuery;
use App\Modules\Workflow\Queries\InoculationWorkbenchQuery;
use App\Modules\Workflow\Queries\WorkbenchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pantalla principal: contadores de las mesadas de la sede activa que el usuario puede ver.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, InoculationWorkbenchQuery $inoculation, WorkbenchQuery $workbenches, BloodCultureWorkbenchQuery $bloodCultures): Response
    {
        $site = $request->attributes->get('activeSite');
        $user = $request->user();

        $counters = [];
        if ($site instanceof Site && $user !== null) {
            if ($user->can(Permission::CulturesInoculate->value)) {
                $counters['inoculation'] = $inoculation->counters($site->id);
            }

            if (collect(BloodCultureWorkbenchController::PERMISSIONS)->contains(fn (Permission $p) => $user->can($p->value))) {
                $counters['blood_cultures'] = $bloodCultures->counters($site->id);
            }

            foreach (Workbench::cases() as $workbench) {
                if (collect($workbench->permissions())->contains(fn (Permission $p) => $user->can($p->value))) {
                    $counters[$workbench->value] = $workbenches->counters($workbench, $site->id);
                }
            }
        }

        return Inertia::render('Dashboard', ['counters' => $counters]);
    }
}
