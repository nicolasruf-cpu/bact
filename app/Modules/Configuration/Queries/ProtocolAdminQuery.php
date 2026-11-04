<?php

namespace App\Modules\Configuration\Queries;

use App\Models\User;
use App\Modules\Configuration\Enums\Atmosphere;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\Medium;
use App\Modules\Configuration\Models\Protocol;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Configuration\Services\AstPanelResolver;
use App\Modules\Configuration\Services\ProtocolVersionRules;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de protocolos y versiones para la configuración clínica.
 */
class ProtocolAdminQuery
{
    public function __construct(
        private readonly ProtocolVersionRules $rules,
        private readonly AstPanelResolver $panels,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return Protocol::query()
            ->with(['study', 'sampleType', 'versions' => fn ($q) => $q->orderByDesc('version')])
            ->get()
            ->sortBy(fn (Protocol $p) => $p->study->name.' '.$p->sampleType->name)
            ->map(fn (Protocol $protocol) => [
                'id' => $protocol->id,
                'name' => $protocol->name,
                'study' => $protocol->study->name,
                'study_code' => $protocol->study->code,
                'sample_type' => $protocol->sampleType->name,
                'is_blood' => $protocol->versions->first()?->processing_kind === ProcessingKind::BloodBottle,
                'versions' => $protocol->versions->map(fn (ProtocolVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'status' => $v->status->value,
                    'status_label' => $v->status->label(),
                    'is_reference_template' => $v->is_reference_template,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(ProtocolVersion $version): array
    {
        $version->load(['protocol.study', 'protocol.sampleType', 'media.medium']);
        $users = DB::table('users')->whereIn('id', array_filter([$version->approved_by, $version->created_by]))->pluck('name', 'id');

        return [
            'id' => $version->id,
            'protocol' => [
                'id' => $version->protocol->id,
                'name' => $version->protocol->name,
                'study' => $version->protocol->study->name,
                'sample_type' => $version->protocol->sampleType->name,
            ],
            'version' => $version->version,
            'status' => $version->status->value,
            'status_label' => $version->status->label(),
            'processing_kind' => $version->processing_kind->value,
            'is_reference_template' => $version->is_reference_template,
            'lock_version' => $version->lock_version,
            'created_by' => $version->created_by ? ($users[$version->created_by] ?? null) : null,
            'approved_by' => $version->approved_by ? ($users[$version->approved_by] ?? null) : null,
            'approved_at' => $version->approved_at?->toIso8601String(),
            'activated_at' => $version->activated_at?->toIso8601String(),
            'retired_at' => $version->retired_at?->toIso8601String(),
            'samples_count' => DB::table('sample_studies')->where('protocol_version_id', $version->id)->count(),
            'missing' => $version->status === ProtocolVersionStatus::Draft ? $this->rules->missingItems($version) : [],
            'ast_panels' => $this->astPanels($version->protocol->sample_type_id),
            'content' => [
                'notes' => $version->notes,
                'source' => $version->source,
                'reading_schedule' => $version->reading_schedule === null ? $version->readingSchedule() : $version->reading_schedule,
                'reincubation_hours' => $version->reincubation_hours,
                'max_reincubations' => $version->max_reincubations,
                'allow_bulk_negative_confirmation' => $version->allow_bulk_negative_confirmation,
                'reading_options' => $version->reading_options ?? [],
                'count_categories' => $version->count_categories ?? [],
                'ast_read_min_hours' => $version->ast_read_min_hours,
                'ast_read_due_hours' => $version->ast_read_due_hours,
                'allow_presumptive_ast' => $version->allow_presumptive_ast,
                'report_non_significant' => $version->report_non_significant,
                'no_new_growth_label' => $version->no_new_growth_label,
                'intermediate_no_growth_label' => $version->intermediate_no_growth_label,
                'media' => $version->media->map(fn ($m) => [
                    'medium_id' => $m->medium_id,
                    'inoculum' => $m->inoculum,
                    'incubation_temperature' => $m->incubation_temperature,
                    'atmosphere' => $m->atmosphere->value,
                    'is_enrichment' => $m->is_enrichment,
                    'enrichment_subculture_min_hours' => $m->enrichment_subculture_min_hours,
                    'enrichment_subculture_due_hours' => $m->enrichment_subculture_due_hours,
                    'enrichment_subculture_media' => $m->enrichment_subculture_media ?? [],
                    'read_from_hours' => $m->read_from_hours,
                    'read_until_hours' => $m->read_until_hours,
                    'bottle_types' => $m->bottle_types ?? [],
                ])->values()->all(),
                'direct_exam_settings' => $version->direct_exam_settings,
                'blood_culture_settings' => $version->blood_culture_settings,
            ],
            'siblings' => ProtocolVersion::query()->where('protocol_id', $version->protocol_id)->orderByDesc('version')->get()
                ->map(fn (ProtocolVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'status' => $v->status->value,
                    'status_label' => $v->status->label(),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editorOptions(): array
    {
        return [
            'media' => Medium::query()->orderBy('name')->get()
                ->map(fn (Medium $m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name, 'is_active' => $m->is_active])
                ->values()->all(),
            'atmospheres' => array_map(fn (Atmosphere $a) => ['value' => $a->value, 'label' => $a->label()], Atmosphere::cases()),
            'outcomes' => array_map(fn (ReadingOutcome $o) => [
                'value' => $o->value,
                'label' => $this->outcomeLabel($o),
                'description' => $this->outcomeDescription($o),
            ], ReadingOutcome::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createOptions(): array
    {
        return [
            'studies' => Study::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->toArray(),
            'sampleTypes' => SampleType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->toArray(),
            'kinds' => [
                ['value' => ProcessingKind::Plate->value, 'label' => 'Cultivo en placa'],
                ['value' => ProcessingKind::BloodBottle->value, 'label' => 'Frasco de hemocultivo'],
            ],
        ];
    }

    /**
     * @return array{draft: bool, approve: bool}
     */
    public function abilities(User $user): array
    {
        return [
            'draft' => $user->can(Permission::ConfigClinicalDraft->value),
            'approve' => $user->can(Permission::CLINICAL_APPROVE),
        ];
    }

    private function outcomeLabel(ReadingOutcome $outcome): string
    {
        return match ($outcome) {
            ReadingOutcome::NoGrowth => 'Sin desarrollo',
            ReadingOutcome::Growth => 'Desarrollo (carga aislamientos)',
            ReadingOutcome::MixedFlora => 'Flora mixta',
            ReadingOutcome::NotSignificant => 'No significativo',
            ReadingOutcome::Reincubate => 'Reincubar',
        };
    }

    /**
     * Qué hace el sistema al elegir la opción en la mesada (RecordReading).
     */
    private function outcomeDescription(ReadingOutcome $outcome): string
    {
        return match ($outcome) {
            ReadingOutcome::NoGrowth => 'Si quedan lecturas, el cultivo espera la siguiente; en la última, se cierra como negativo.',
            ReadingOutcome::Growth => 'Pide cargar los aislamientos con su recuento y los pasa a identificación.',
            ReadingOutcome::MixedFlora => 'Cierra el cultivo como contaminado (flora mixta), sin cargar aislamientos.',
            ReadingOutcome::NotSignificant => 'Cierra el cultivo como no significativo, sin cargar aislamientos.',
            ReadingOutcome::Reincubate => 'Vuelve a programar la lectura según las horas de reincubación, hasta el máximo de veces indicado.',
        };
    }

    /**
     * Qué panel de antibiograma le toca a cada grupo en la muestra del protocolo (D-0021).
     *
     * @return array<int, array{group: string, label: string, panels: array<int, array{name: string, is_vitek: bool, all_samples: bool}>}>
     */
    private function astPanels(int $sampleTypeId): array
    {
        return array_map(fn (OrganismGroup $group) => [
            'group' => $group->value,
            'label' => $group->label(),
            'panels' => $this->panels->candidates($group, $sampleTypeId)
                ->map(fn (AstPanel $p) => ['name' => $p->name, 'is_vitek' => $p->is_vitek, 'all_samples' => $p->sample_types_count === 0])
                ->values()
                ->all(),
        ], OrganismGroup::cases());
    }
}
