<?php

namespace App\Modules\Cultures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CriticalResults\Enums\CommunicationChannel;
use App\Modules\CriticalResults\Queries\StudyCriticalAlertsQuery;
use App\Modules\Cultures\Actions\RecordDirectExam;
use App\Modules\Cultures\Queries\DirectExamQuery;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Queries\StudyReportsQuery;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Samples\Queries\SampleHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Examen directo de un estudio: registro, resultado crítico (si corresponde) e informe
 * preliminar. La lógica está en las acciones.
 */
class DirectExamController extends Controller
{
    public function show(
        Request $request,
        SampleStudy $sampleStudy,
        SampleHeader $header,
        DirectExamQuery $exam,
        StudyCriticalAlertsQuery $alerts,
        StudyReportsQuery $reports,
    ): Response {
        $this->authorizeSite($sampleStudy);
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can(Permission::DirectExamsPerform->value) || $user->can(Permission::ReportsValidate->value), 403);

        $settings = $sampleStudy->protocolVersion->directExamSettings();
        abort_if($settings === null, 404);

        return Inertia::render('cultures/DirectExam', [
            'sample' => $header->for($sampleStudy),
            'studyStatus' => $sampleStudy->status->value,
            'settings' => [
                'label' => $settings->label,
                'fields' => $settings->fields,
                'critical_if_organisms' => $settings->criticalIfOrganisms,
            ],
            'exam' => $exam->forStudy($sampleStudy),
            'criticalAlerts' => $alerts->forStudy($sampleStudy->id),
            'reports' => $reports->forStudy($sampleStudy->id),
            'channels' => collect(CommunicationChannel::cases())
                ->map(fn (CommunicationChannel $c) => ['value' => $c->value, 'label' => $c->label()])->all(),
            'can' => [
                'perform' => $user->can(Permission::DirectExamsPerform->value),
                'communicate' => $user->can(Permission::CriticalCommunicate->value),
                'preliminary' => $user->can(Permission::ReportsValidate->value),
            ],
        ]);
    }

    public function store(Request $request, SampleStudy $sampleStudy, RecordDirectExam $record): RedirectResponse
    {
        $this->authorizeSite($sampleStudy);

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*' => ['array'],
            'results.*.*' => ['string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['results' => 'resultado', 'description' => 'descripción']);

        /** @var User $user */
        $user = $request->user();
        /** @var array<string, list<string>> $results */
        $results = array_map(fn ($codes) => array_values((array) $codes), $validated['results']);
        $exam = $record->handle($user, $sampleStudy, $results, $validated['description'] ?? null);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $exam->organisms_seen && $sampleStudy->protocolVersion->directExamSettings()?->criticalIfOrganisms
                ? 'Directo registrado: hay un resultado crítico para comunicar.'
                : 'Examen directo registrado.',
        ]);

        return to_route('direct-exams.show', $sampleStudy->id);
    }

    private function authorizeSite(SampleStudy $study): void
    {
        abort_unless($study->processing_site_id === getPermissionsTeamId(), 403);
    }
}
