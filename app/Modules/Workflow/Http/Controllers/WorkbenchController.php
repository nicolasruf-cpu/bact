<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Organization\Models\Site;
use App\Modules\Workflow\Enums\Workbench;
use App\Modules\Workflow\Queries\WorkbenchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkbenchController extends Controller
{
    public function show(Request $request, Workbench $workbench, WorkbenchQuery $query): Response
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        $user = $request->user();
        $allowed = collect($workbench->permissions())->contains(fn (Permission $p): bool => (bool) $user?->can($p->value));
        abort_unless($allowed, 403);

        return Inertia::render('workflow/Workbench', [
            'workbench' => ['key' => $workbench->value, 'title' => $workbench->title()],
            'items' => $query->items($workbench, $site->id),
            'counters' => $query->counters($workbench, $site->id),
        ]);
    }
}
