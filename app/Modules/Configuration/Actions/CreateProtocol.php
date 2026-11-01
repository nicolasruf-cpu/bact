<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Protocolo nuevo (estudio + tipo de muestra) con su primera versión en borrador, vacía:
 * el contenido lo carga el laboratorio y lo aprueba el responsable (Q-A8).
 */
class CreateProtocol
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, int $studyId, int $sampleTypeId, string $name, ProcessingKind $kind): ProtocolVersion
    {
        if (! $user->can(Permission::ConfigClinicalDraft->value)) {
            throw new AuthorizationException('No tenés permiso para preparar configuración clínica.');
        }

        return DB::transaction(function () use ($user, $studyId, $sampleTypeId, $name, $kind) {
            $study = Study::query()->where('is_active', true)->find($studyId);
            $sampleType = SampleType::query()->where('is_active', true)->find($sampleTypeId);
            if ($study === null || $sampleType === null) {
                throw ValidationException::withMessages(['study_id' => 'Elegí un estudio y un tipo de muestra activos.']);
            }

            if (Protocol::query()->where('study_id', $studyId)->where('sample_type_id', $sampleTypeId)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['study_id' => 'Ya hay un protocolo para ese estudio y tipo de muestra: prepará una versión nueva.']);
            }

            $protocol = Protocol::create([
                'study_id' => $studyId,
                'sample_type_id' => $sampleTypeId,
                'name' => trim($name) !== '' ? trim($name) : "{$study->name} — {$sampleType->name}",
            ]);

            /** @var ProtocolVersion $version */
            $version = $protocol->versions()->create([
                'version' => 1,
                'status' => ProtocolVersionStatus::Draft,
                'is_reference_template' => false,
                'processing_kind' => $kind,
                'first_read_min_hours' => 0,
                'first_read_due_hours' => 0,
                'reading_schedule' => [],
                'max_reincubations' => 0,
                'allow_bulk_negative_confirmation' => false,
                'reading_options' => [],
                'count_categories' => [],
                'created_by' => $user->id,
            ]);

            $this->auditor->record(ConfigurationAuditAction::ProtocolCreated, $protocol, after: [
                'study' => $study->code,
                'sample_type' => $sampleType->code,
                'name' => $protocol->name,
                'processing_kind' => $kind->value,
            ]);

            return $version;
        });
    }
}
