<?php

namespace App\Modules\Statistics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Site;
use App\Modules\Statistics\Queries\SusceptibilityProfileQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class SusceptibilityProfileController extends Controller
{
    public function __invoke(Request $request, SusceptibilityProfileQuery $query): Response
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? Date::parse($validated['to']) : Date::now();
        $from = isset($validated['from']) ? Date::parse($validated['from']) : $to->copy()->subYear();

        return Inertia::render('statistics/SusceptibilityProfile', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'site' => $site->name,
            'minIsolates' => SusceptibilityProfileQuery::MIN_ISOLATES,
            ...$query->profile($site->id, $from, $to),
        ]);
    }
}
