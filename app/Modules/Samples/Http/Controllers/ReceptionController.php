<?php

namespace App\Modules\Samples\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Orders\Enums\Priority;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Enums\Sex;
use App\Modules\Samples\Actions\ReceiveSample;
use App\Modules\Samples\Http\Requests\ReceiveSampleRequest;
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
            // Se pide con una recarga parcial al salir del campo documento.
            'patientMatch' => Inertia::optional(fn () => $query->patientByDocument($request->string('document')->toString())),
        ]);
    }

    public function store(ReceiveSampleRequest $request, ReceiveSample $receiveSample): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sample = $receiveSample->handle($user, $this->activeSite($request), $request->toData());

        $studies = $sample->studies()->count();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Muestra {$sample->barcode} recibida: {$studies} ".($studies === 1 ? 'siembra pendiente' : 'siembras pendientes').'.',
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
