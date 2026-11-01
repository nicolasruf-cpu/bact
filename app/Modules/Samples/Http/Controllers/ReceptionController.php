<?php

namespace App\Modules\Samples\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Enums\Sex;
use App\Modules\Samples\Actions\ReceiveOrderSamples;
use App\Modules\Samples\Http\Requests\ReceiveOrderSamplesRequest;
use App\Modules\Samples\Queries\ReceptionQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    public function create(Request $request, ReceptionQuery $query): Response
    {
        $site = $this->activeSite($request);

        return Inertia::render('samples/Reception', [
            ...$query->catalogs(),
            'priorities' => array_map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()], Priority::cases()),
            'sexes' => array_map(fn (Sex $s) => ['value' => $s->value, 'label' => $s->label()], Sex::cases()),
            'receivedToday' => $query->receivedToday($site->id),
            // Se piden con recargas parciales al salir de los campos orden y documento.
            'orderMatch' => Inertia::optional(fn () => $query->orderByNumber($site->id, $request->string('order')->toString())),
            'patientMatch' => Inertia::optional(fn () => $query->patientByDocument($request->string('document')->toString())),
        ]);
    }

    public function store(ReceiveOrderSamplesRequest $request, ReceiveOrderSamples $receive): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $reception = $receive->handle($user, $this->activeSite($request), $request->toData());

        $count = $reception->samples()->count();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1 ? '1 muestra recibida.' : "{$count} muestras recibidas.",
        ]);

        return to_route('reception.create');
    }

    private function activeSite(Request $request): Site
    {
        $site = $request->attributes->get('activeSite');
        abort_unless($site instanceof Site, 403, 'No tenés una sede activa.');

        return $site;
    }
}
