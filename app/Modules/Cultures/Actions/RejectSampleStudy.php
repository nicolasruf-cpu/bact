<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Events\SampleStudyRejected;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Cultures\Services\CultureClosure;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Declara una muestra no apta para el estudio (revisión de marchas B8): por ejemplo, un
 * esputo de mala calidad por el directo, o una orina con flora mixta. El criterio lo aplica
 * el bacteriólogo y queda el motivo. Si se pide una nueva muestra, queda a quién se le pidió.
 * Cierra el cultivo, si lo hay, y el estudio pasa a validación: el informe dice que la
 * muestra no fue apta, con el motivo. Workflow cancela lo pendiente.
 */
class RejectSampleStudy
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly CultureClosure $closure,
    ) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, SampleStudy $study, string $reason, ?string $newSampleRequestedTo): SampleStudy
    {
        if (! $user->can(Permission::SamplesReject->value) && ! $user->can(Permission::CulturesReadPositive->value)) {
            throw new AuthorizationException('No tenés permiso para declarar una muestra no apta.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Indicá por qué la muestra no es apta.']);
        }

        return DB::transaction(function () use ($user, $study, $reason, $newSampleRequestedTo) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->findOrFail($study->id);
            if (! in_array($study->status, [SampleStudyStatus::Pending, SampleStudyStatus::InProcess, SampleStudyStatus::PreliminaryReported], true)) {
                throw ValidationException::withMessages(['reason' => 'El estudio ya está cerrado.']);
            }

            /** @var Culture|null $culture */
            $culture = Culture::query()->where('sample_study_id', $study->id)->lockForUpdate()->latest('id')->first();
            if ($culture !== null && $culture->isolates()->get()->contains(fn (Isolate $i) => $i->status === IsolateStatus::Resolved)) {
                throw ValidationException::withMessages(['reason' => 'El cultivo ya tiene aislamientos resueltos: no se puede declarar la muestra no apta.']);
            }

            $study->update([
                'rejected_at' => Date::now(),
                'rejected_by' => $user->id,
                'rejection_reason' => trim($reason),
                'new_sample_requested_to' => filled($newSampleRequestedTo) ? trim((string) $newSampleRequestedTo) : null,
                'lock_version' => $study->lock_version + 1,
            ]);

            $this->auditor->record(CultureAuditAction::SampleRejected, $study, after: [
                'new_sample_requested_to' => $study->new_sample_requested_to,
            ], reason: $reason);

            event(new SampleStudyRejected($study, $culture, $user->id));

            if ($culture !== null && ! $culture->status->isClosed()) {
                $culture->isolates()->whereNot('status', IsolateStatus::NotSignificant->value)
                    ->update(['status' => IsolateStatus::NotSignificant->value]);
                $this->closure->close($culture, CultureStatus::ClosedRejected);
            } elseif ($culture === null) {
                $this->closure->closeStudy($study);
            }

            return $study;
        });
    }
}
