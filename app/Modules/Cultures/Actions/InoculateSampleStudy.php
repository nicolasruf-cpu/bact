<?php

namespace App\Modules\Cultures\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\ProtocolVersionMedium;
use App\Modules\Cultures\Enums\CultureAuditAction;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Services\CultureStarter;
use App\Modules\Samples\Enums\SampleStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use App\Modules\Samples\Models\SampleStudy;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siembra de un estudio: crea el cultivo y sus placas según la versión de protocolo
 * congelada. Si se omite algún medio del protocolo, hace falta un motivo.
 * Idempotente: un estudio ya sembrado devuelve su cultivo.
 */
class InoculateSampleStudy
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly CultureStarter $starter,
    ) {}

    /**
     * @param  list<int>|null  $mediumIds  medios efectivamente sembrados (null = todos los del protocolo)
     * @param  array<string, mixed>  $context  contexto para la auditoría (p. ej., tanda)
     *
     * @throws ValidationException
     */
    public function handle(User $user, SampleStudy $study, ?array $mediumIds = null, ?string $reason = null, array $context = []): Culture
    {
        return DB::transaction(function () use ($user, $study, $mediumIds, $reason, $context) {
            /** @var SampleStudy $study */
            $study = SampleStudy::query()->lockForUpdate()->with(['protocolVersion.media.medium', 'sample'])->findOrFail($study->id);

            $existing = Culture::query()->where('sample_study_id', $study->id)->where('attempt', 1)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($study->protocolVersion->isBloodCulture()) {
                throw ValidationException::withMessages(['study' => 'Los hemocultivos no se siembran en placa: se cargan como frasco.']);
            }

            if ($study->status !== SampleStudyStatus::Pending) {
                throw ValidationException::withMessages(['study' => 'Este estudio no está pendiente de siembra.']);
            }

            $protocolMedia = $study->protocolVersion->media;
            $selected = $mediumIds === null
                ? $protocolMedia
                : $protocolMedia->filter(fn (ProtocolVersionMedium $m) => in_array($m->medium_id, $mediumIds, true));

            if ($selected->isEmpty()) {
                throw ValidationException::withMessages(['medium_ids' => 'Hay que sembrar al menos un medio.']);
            }

            $modified = $selected->count() !== $protocolMedia->count();
            if ($modified && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Si no se siembran todos los medios del protocolo, indicá el motivo.']);
            }

            $culture = $this->starter->start(
                $study,
                $selected->map(fn (ProtocolVersionMedium $m) => [
                    'medium_id' => $m->medium_id,
                    'inoculum' => $m->inoculum,
                    'incubation_temperature' => $m->incubation_temperature,
                    'atmosphere' => $m->atmosphere,
                    'is_enrichment' => $m->is_enrichment,
                ])->all(),
                Date::now(),
                $user->id,
                $reason,
            );

            $study->update(['status' => SampleStudyStatus::InProcess, 'lock_version' => $study->lock_version + 1]);
            if ($study->sample->status === SampleStatus::Received) {
                $study->sample->update(['status' => SampleStatus::InProcess, 'lock_version' => $study->sample->lock_version + 1]);
            }

            $this->auditor->record(
                CultureAuditAction::Inoculated,
                $culture,
                after: [
                    'sample_id' => $study->sample_id,
                    'sample_study_id' => $study->id,
                    'media' => $selected->map(fn (ProtocolVersionMedium $m) => $m->medium->code)->values()->all(),
                    'modified' => $modified,
                ],
                reason: $modified ? $reason : null,
                context: $context,
            );

            return $culture;
        });
    }
}
