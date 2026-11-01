<?php

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Identification\Models\Identification;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Enums\ReportAuditAction;
use App\Modules\Reporting\Enums\ReportStatus;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Events\ReportValidated;
use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Services\ReportBuilder;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Valida un estudio listo y emite el informe definitivo (contenido congelado).
 * Cada informe se valida individualmente (Q-F4).
 */
class ValidateReport
{
    public function __construct(
        private readonly ReportBuilder $builder,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @throws ValidationException|AuthorizationException
     */
    public function handle(User $user, SampleStudy $study): Report
    {
        return DB::transaction(function () use ($user, $study) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->with('sample')->findOrFail($study->id);

            $existing = Report::query()->where('sample_study_id', $study->id)
                ->where('type', ReportType::Final->value)
                ->where('status', ReportStatus::Issued->value)
                ->first();
            if ($existing !== null && $study->status === SampleStudyStatus::FinalReported) {
                return $existing;
            }

            if ($study->status !== SampleStudyStatus::ReadyForValidation) {
                throw ValidationException::withMessages(['study' => 'El estudio no está listo para validar.']);
            }

            $this->authorize($user, $study);

            $report = Report::create([
                'sample_study_id' => $study->id,
                'version' => (int) Report::query()->where('sample_study_id', $study->id)->max('version') + 1,
                'type' => ReportType::Final,
                'status' => ReportStatus::Issued,
                'snapshot' => $this->builder->build($study),
                'validated_by' => $user->id,
                'validated_at' => Date::now(),
            ]);

            // El definitivo reemplaza a los preliminares emitidos.
            Report::query()
                ->where('sample_study_id', $study->id)
                ->where('type', ReportType::Preliminary->value)
                ->where('status', ReportStatus::Issued->value)
                ->where('id', '!=', $report->id)
                ->update(['status' => ReportStatus::Superseded->value]);

            $study->update(['status' => SampleStudyStatus::FinalReported, 'lock_version' => $study->lock_version + 1]);

            $sample = $study->sample;
            $allFinal = $sample->studies()->get()->every(
                fn (SampleStudy $s) => in_array($s->status, [SampleStudyStatus::FinalReported, SampleStudyStatus::Cancelled], true),
            );
            $sample->update([
                'status' => $allFinal ? SampleStatus::Reported : SampleStatus::PartiallyReported,
                'lock_version' => $sample->lock_version + 1,
            ]);

            $this->auditor->record(ReportAuditAction::Validated, $report, after: [
                'sample_study_id' => $study->id,
                'version' => $report->version,
                'snapshot_hash' => hash('sha256', (string) json_encode($report->snapshot)),
            ]);

            event(new ReportValidated($report, $study, $user->id));

            return $report;
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $user, SampleStudy $study): void
    {
        if (! $user->can(Permission::ReportsValidate->value)) {
            throw new AuthorizationException('No tenés permiso para validar informes.');
        }

        if ($user->can(Permission::ReportsValidateOwnWork->value)) {
            return;
        }

        // Sin "validar trabajo propio": no puede validar quien leyó el cultivo, identificó o leyó el antibiograma.
        $culture = Culture::query()->where('sample_study_id', $study->id)->with('readings', 'isolates')->first();
        $isolateIds = $culture?->isolates->pluck('id')->all() ?? [];
        $workedOnIt = $culture !== null && (
            $culture->readings->contains('read_by', $user->id)
            || Identification::query()->whereIn('isolate_id', $isolateIds)->where('identified_by', $user->id)->exists()
            || SusceptibilityTest::query()->whereIn('isolate_id', $isolateIds)->where('read_by', $user->id)->exists()
        );

        if ($workedOnIt) {
            throw new AuthorizationException('No podés validar un estudio que procesaste (se requiere otro validador).');
        }
    }
}
