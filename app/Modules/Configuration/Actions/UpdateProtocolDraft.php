<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Services\ProtocolVersionRules;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda el contenido de un borrador. Solo los borradores se modifican: una versión
 * aprobada o vigente es inmutable (las muestras la congelan).
 */
class UpdateProtocolDraft
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly ProtocolVersionRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ProtocolVersion $version, array $data, int $lockVersion): ProtocolVersion
    {
        if (! $user->can(Permission::ConfigClinicalDraft->value)) {
            throw new AuthorizationException('No tenés permiso para preparar configuración clínica.');
        }

        return DB::transaction(function () use ($version, $data, $lockVersion) {
            /** @var ProtocolVersion $version */
            $version = ProtocolVersion::query()->lockForUpdate()->with('media.medium')->findOrFail($version->id);

            if ($version->status !== ProtocolVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'Solo se modifica un borrador.']);
            }
            if ($version->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este borrador. Recargá la página.']);
            }

            $normalized = $this->rules->normalize($data, $version->processing_kind);

            $before = $this->snapshot($version);

            $version->update([...$normalized['version'], 'lock_version' => $version->lock_version + 1]);
            $version->media()->delete();
            foreach ($normalized['media'] as $medium) {
                $version->media()->create($medium);
            }
            $version->load('media.medium');

            $after = $this->snapshot($version);
            $changedKeys = array_keys(array_filter($after, fn ($value, $key) => $before[$key] != $value, ARRAY_FILTER_USE_BOTH));

            if ($changedKeys !== []) {
                $this->auditor->record(ConfigurationAuditAction::ProtocolVersionUpdated, $version,
                    before: array_intersect_key($before, array_flip($changedKeys)),
                    after: array_intersect_key($after, array_flip($changedKeys)),
                );
            }

            return $version;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProtocolVersion $version): array
    {
        return [
            ...$version->only([
                'notes', 'source', 'reading_schedule', 'reincubation_hours', 'max_reincubations',
                'allow_bulk_negative_confirmation', 'reading_options', 'count_categories',
                'ast_read_min_hours', 'ast_read_due_hours', 'direct_exam_settings', 'blood_culture_settings',
                'allow_presumptive_ast', 'report_non_significant', 'no_new_growth_label', 'intermediate_no_growth_label',
            ]),
            'media' => $version->media->map(fn ($m) => [
                'medium' => $m->medium->code,
                'inoculum' => $m->inoculum,
                'incubation_temperature' => $m->incubation_temperature,
                'atmosphere' => $m->atmosphere->value,
                'is_enrichment' => $m->is_enrichment,
                'enrichment_subculture_min_hours' => $m->enrichment_subculture_min_hours,
                'enrichment_subculture_due_hours' => $m->enrichment_subculture_due_hours,
                'enrichment_subculture_media' => $m->enrichment_subculture_media,
                'read_from_hours' => $m->read_from_hours,
                'read_until_hours' => $m->read_until_hours,
                'bottle_types' => $m->bottle_types,
            ])->all(),
        ];
    }
}
