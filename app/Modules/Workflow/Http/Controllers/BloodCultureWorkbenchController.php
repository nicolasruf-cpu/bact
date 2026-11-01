<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Organization\Models\Site;
use App\Modules\Workflow\Queries\BloodCultureWorkbenchQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BloodCultureWorkbenchController extends Controller
{
    /** Con cualquiera de estos permisos se ve la mesada. */
    public const PERMISSIONS = [
        Permission::BloodCulturesLoad,
        Permission::BloodCulturesSignalPositive,
        Permission::BloodCulturesConfirmNegative,
        Permission::GramPerform,
    ];

    public function index(Request $request, BloodCultureWorkbenchQuery $query): Response
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        $user = $request->user();
        abort_unless(collect(self::PERMISSIONS)->contains(fn (Permission $p): bool => (bool) $user?->can($p->value)), 403);

        return Inertia::render('blood-cultures/Workbench', [
            'items' => $query->items($site->id),
            'incubating' => $query->incubating($site->id),
            'counters' => $query->counters($site->id),
        ]);
    }
}
