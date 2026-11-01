<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Versión nueva en borrador, copiada de otra del mismo protocolo. Hay a lo sumo un
 * borrador por protocolo: si ya existe, se devuelve ese.
 * Si la versión de origen era plantilla de referencia (D-0008), la copia también lo es.
 */
class DraftProtocolVersion
{
    private const COPIED = [
        'processing_kind', 'is_reference_template', 'source', 'notes', 'first_read_min_hours', 'first_read_due_hours',
        'reading_schedule', 'reincubation_hours', 'max_reincubations', 'allow_bulk_negative_confirmation',
        'reading_options', 'count_categories', 'blood_culture_settings', 'direct_exam_settings',
        'ast_read_min_hours', 'ast_read_due_hours',
    ];

    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, ProtocolVersion $from): ProtocolVersion
    {
        if (! $user->can(Permission::ConfigClinicalDraft->value)) {
            throw new AuthorizationException('No tenés permiso para preparar configuración clínica.');
        }

        return DB::transaction(function () use ($user, $from) {
            Protocol::query()->lockForUpdate()->findOrFail($from->protocol_id);

            $existing = ProtocolVersion::query()
                ->where('protocol_id', $from->protocol_id)
                ->where('status', ProtocolVersionStatus::Draft->value)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $from->loadMissing('media');
            $number = (int) ProtocolVersion::query()->where('protocol_id', $from->protocol_id)->max('version') + 1;

            /** @var ProtocolVersion $draft */
            $draft = ProtocolVersion::create([
                ...$from->only(self::COPIED),
                // Las versiones sin cronograma explícito tienen una sola lectura (la primera).
                'reading_schedule' => $from->readingSchedule(),
                'protocol_id' => $from->protocol_id,
                'version' => $number,
                'status' => ProtocolVersionStatus::Draft,
                'created_by' => $user->id,
            ]);

            foreach ($from->media as $medium) {
                $draft->media()->create($medium->only([
                    'medium_id', 'position', 'inoculum', 'incubation_temperature', 'atmosphere',
                    'is_enrichment', 'enrichment_subculture_min_hours', 'enrichment_subculture_due_hours',
                    'enrichment_subculture_media',
                ]));
            }

            $this->auditor->record(ConfigurationAuditAction::ProtocolVersionDrafted, $draft, after: [
                'protocol_id' => $draft->protocol_id,
                'version' => $number,
                'copied_from_version' => $from->version,
            ]);

            return $draft;
        });
    }
}
