<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cultures\Actions\InoculateSampleStudy;
use App\Modules\Cultures\Actions\InoculateWithStandardProtocol;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Registro de siembra, individual o en bloque (protocolo estándar).
 * El contexto de tanda viaja solo para la auditoría: Cultures no conoce las tandas.
 */
class InoculationController extends Controller
{
    public function store(Request $request, SampleStudy $sampleStudy, InoculateSampleStudy $inoculate): RedirectResponse
    {
        $user = $this->authorizeFor($request, $sampleStudy);

        $validated = $request->validate([
            'medium_ids' => ['nullable', 'array'],
            'medium_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
            'batch' => ['nullable', 'string', 'size:26'],
        ], [], ['medium_ids' => 'medios', 'reason' => 'motivo']);

        $inoculate->handle(
            $user,
            $sampleStudy,
            isset($validated['medium_ids']) ? array_values(array_map('intval', $validated['medium_ids'])) : null,
            $validated['reason'] ?? null,
            array_filter(['batch' => $validated['batch'] ?? null]),
        );

        return back();
    }

    /**
     * Confirmación múltiple: siembra con el protocolo estándar, sin modificaciones
     * (ACTIVITY_CATALOG: INOCULATE admite confirmación múltiple solo en ese caso).
     */
    public function bulk(Request $request, InoculateWithStandardProtocol $inoculate): RedirectResponse
    {
        $validated = $request->validate([
            'sample_study_ids' => ['required', 'array', 'min:1', 'max:200'],
            'sample_study_ids.*' => ['integer', 'distinct'],
            'batch' => ['nullable', 'string', 'size:26'],
        ]);

        $studies = SampleStudy::query()->whereIn('id', $validated['sample_study_ids'])->get();
        abort_unless($studies->count() === count($validated['sample_study_ids']), 404);

        $user = null;
        foreach ($studies as $study) {
            $user = $this->authorizeFor($request, $study);
        }

        /** @var User $user */
        $count = $inoculate->handle($user, $studies, array_filter(['batch' => $validated['batch'] ?? null]));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} siembras registradas con el protocolo estándar."]);

        return back();
    }

    private function authorizeFor(Request $request, SampleStudy $study): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::CulturesInoculate->value), 403);
        // Solo la sede que procesa el estudio puede sembrarlo (ADR-0008).
        abort_unless($study->processing_site_id === getPermissionsTeamId(), 403);

        return $user;
    }
}
