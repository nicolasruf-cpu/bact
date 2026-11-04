<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Cultures\Actions\MarkPlateLost;
use App\Modules\Cultures\Actions\RejectSampleStudy;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Incidentes del cultivo: placa perdida (B7) y muestra no apta (B8).
 */
class CultureIncidentController extends Controller
{
    public function plateLost(Request $request, Plate $plate, MarkPlateLost $mark): RedirectResponse
    {
        abort_unless($plate->culture->sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'resow' => ['required', 'boolean'],
        ], [], ['reason' => 'motivo']);

        $replacement = $mark->handle($this->user($request), $plate, $validated['reason'], (bool) $validated['resow']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $replacement !== null
            ? 'Placa perdida. Se resembró: la lectura espera su incubación.'
            : 'Placa perdida registrada.']);

        return back();
    }

    public function reject(Request $request, SampleStudy $sampleStudy, RejectSampleStudy $reject): RedirectResponse
    {
        abort_unless($sampleStudy->processing_site_id === getPermissionsTeamId(), 403);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'new_sample_requested_to' => ['nullable', 'string', 'max:255'],
        ], [], ['reason' => 'motivo']);

        $reject->handle($this->user($request), $sampleStudy, $validated['reason'], $validated['new_sample_requested_to'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Muestra declarada no apta. El estudio pasa a validación.']);

        return to_route('dashboard');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
