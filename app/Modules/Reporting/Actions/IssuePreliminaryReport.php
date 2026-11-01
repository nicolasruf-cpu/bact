<?php

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Enums\ReportAuditAction;
use App\Modules\Reporting\Enums\ReportStatus;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Models\Report;
use App\Modules\Reporting\Services\ReportBuilder;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Informe preliminar de un estudio en curso (p. ej., hemocultivo positivo con el Gram).
 * Congela lo registrado hasta ahora; un preliminar nuevo reemplaza al anterior y el
 * definitivo los reemplaza a todos. Lo emite quien puede validar (Q-C7).
 */
class IssuePreliminaryReport
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
        if (! $user->can(Permission::ReportsValidate->value)) {
            throw new AuthorizationException('No tenés permiso para emitir informes.');
        }

        return DB::transaction(function () use ($user, $study) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->findOrFail($study->id);

            if (! in_array($study->status, [SampleStudyStatus::InProcess, SampleStudyStatus::PreliminaryReported], true)) {
                throw ValidationException::withMessages(['study' => 'Solo se emite un preliminar de un estudio en curso.']);
            }

            $snapshot = $this->builder->build($study);

            $previous = Report::query()
                ->where('sample_study_id', $study->id)
                ->where('type', ReportType::Preliminary->value)
                ->where('status', ReportStatus::Issued->value)
                ->latest('version')
                ->first();
            if ($previous !== null && $previous->snapshot == $snapshot) {
                return $previous;
            }

            $report = Report::create([
                'sample_study_id' => $study->id,
                'version' => (int) Report::query()->where('sample_study_id', $study->id)->max('version') + 1,
                'type' => ReportType::Preliminary,
                'status' => ReportStatus::Issued,
                'snapshot' => $snapshot,
                'validated_by' => $user->id,
                'validated_at' => Date::now(),
            ]);

            $previous?->update(['status' => ReportStatus::Superseded]);
            $study->update(['status' => SampleStudyStatus::PreliminaryReported, 'lock_version' => $study->lock_version + 1]);

            $this->auditor->record(ReportAuditAction::PreliminaryIssued, $report, after: [
                'sample_study_id' => $study->id,
                'version' => $report->version,
                'snapshot_hash' => hash('sha256', (string) json_encode($report->snapshot)),
            ]);

            return $report;
        });
    }
}
