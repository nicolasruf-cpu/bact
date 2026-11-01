<?php

namespace App\Modules\Cultures\Services;

use App\Modules\Audit\Services\Auditor;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Enums\CultureStatus;
use App\Modules\Cultures\Enums\IsolateStatus;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Events\CultureCompleted;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Isolate;
use App\Modules\Samples\Enums\SampleStudyStatus;

/**
 * Cierre del cultivo: el estudio queda listo para validar. Se llama dentro de la transacción
 * de la operación que lo provoca (lectura final, o el último aislamiento resuelto).
 */
class CultureClosure
{
    public function __construct(private readonly Auditor $auditor) {}

    public function close(Culture $culture, CultureStatus $status): void
    {
        $culture->update(['status' => $status, 'lock_version' => $culture->lock_version + 1]);
        $culture->plates()->where('status', PlateStatus::Incubating->value)->update(['status' => PlateStatus::Finished->value]);

        $study = $culture->sampleStudy;
        $study->update(['status' => SampleStudyStatus::ReadyForValidation, 'lock_version' => $study->lock_version + 1]);

        $this->auditor->record(CultureAuditAction::Completed, $culture, after: ['status' => $status->value]);

        event(new CultureCompleted($culture, $study));
    }

    /**
     * Marca un aislamiento como resuelto (identificado y, si correspondía, con antibiograma)
     * y cierra el cultivo como positivo si ya no queda ninguno pendiente.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function resolveIsolate(Isolate $isolate, array $attributes = []): void
    {
        $isolate->fill([...$attributes, 'status' => IsolateStatus::Resolved, 'lock_version' => $isolate->lock_version + 1])->save();

        $culture = Culture::query()->lockForUpdate()->findOrFail($isolate->culture_id);
        $pending = $culture->isolates()->get()->contains(fn (Isolate $i) => ! $i->status->isResolved());

        if (! $pending && $culture->status === CultureStatus::Workup) {
            $this->close($culture, CultureStatus::ClosedPositive);
        }
    }
}
