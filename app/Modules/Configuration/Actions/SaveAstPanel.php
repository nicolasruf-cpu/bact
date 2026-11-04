<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta o modificación de un panel de antibiograma (D-0021). Lo hace el Director técnico
 * responsable: el panel no tiene versiones y el cambio vale para los antibiogramas que se
 * preparen desde ahora. Los ya preparados no cambian (copian los antibióticos al prepararse).
 * Al guardarlo deja de ser plantilla de referencia: quedó revisado por el responsable.
 */
class SaveAstPanel
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  array{code?: string, name: string, is_vitek: bool, is_active: bool, organism_groups: list<string>, sample_type_ids: list<int>, antibiotic_ids: list<int>, read_min_hours?: int|null, read_due_hours?: int|null}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ?int $id, array $data, ?int $lockVersion = null): AstPanel
    {
        if (! $user->can(Permission::CLINICAL_APPROVE)) {
            throw new AuthorizationException('Los paneles los define el Director técnico responsable.');
        }

        $name = trim($data['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre.']);
        }

        $groups = [];
        foreach (array_unique($data['organism_groups']) as $value) {
            $group = OrganismGroup::tryFrom($value);
            if ($group === null) {
                throw ValidationException::withMessages(['organism_groups' => 'Hay un grupo de germen que no existe.']);
            }
            $groups[] = $group;
        }
        if ($groups === []) {
            throw ValidationException::withMessages(['organism_groups' => 'Elegí al menos un grupo de germen.']);
        }

        $antibioticIds = array_values(array_unique($data['antibiotic_ids']));
        if ($antibioticIds === []) {
            throw ValidationException::withMessages(['antibiotic_ids' => 'Agregá al menos un antibiótico.']);
        }
        if (Antibiotic::query()->whereIn('id', $antibioticIds)->count() !== count($antibioticIds)) {
            throw ValidationException::withMessages(['antibiotic_ids' => 'Hay un antibiótico que no existe.']);
        }

        $readMin = $data['read_min_hours'] ?? null;
        $readDue = $data['read_due_hours'] ?? null;
        if ($readMin === null && $readDue !== null) {
            throw ValidationException::withMessages(['read_min_hours' => 'Indicá desde qué hora se lee el panel.']);
        }
        if ($readMin !== null && $readDue < $readMin) {
            throw ValidationException::withMessages(['read_due_hours' => 'El vencimiento no puede ser anterior al inicio.']);
        }

        $sampleTypeIds = array_values(array_unique($data['sample_type_ids']));
        if (SampleType::query()->whereIn('id', $sampleTypeIds)->count() !== count($sampleTypeIds)) {
            throw ValidationException::withMessages(['sample_type_ids' => 'Hay un tipo de muestra que no existe.']);
        }

        return DB::transaction(function () use ($id, $data, $name, $groups, $antibioticIds, $sampleTypeIds, $lockVersion, $readMin, $readDue) {
            if ($id === null) {
                $panel = new AstPanel(['code' => $this->newCode($data['code'] ?? '')]);
                $before = null;
            } else {
                /** @var AstPanel $panel */
                $panel = AstPanel::query()->lockForUpdate()->with(['antibiotics', 'sampleTypes'])->findOrFail($id);
                if ($lockVersion !== null && $lockVersion !== $panel->lock_version) {
                    throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este panel. Recargá la página.']);
                }
                $before = $this->snapshot($panel);
            }

            $panel->forceFill([
                'name' => $name,
                'organism_groups' => $groups,
                'is_vitek' => (bool) $data['is_vitek'],
                'read_min_hours' => $readMin,
                'read_due_hours' => $readDue,
                'is_active' => (bool) $data['is_active'],
                'is_reference_template' => false,
                'lock_version' => $id === null ? 0 : $panel->lock_version + 1,
            ])->save();

            $panel->antibiotics()->sync(collect($antibioticIds)
                ->mapWithKeys(fn (int $antibioticId, int $index) => [$antibioticId => ['position' => $index + 1]])
                ->all());
            $panel->sampleTypes()->sync($sampleTypeIds);
            $panel->load(['antibiotics', 'sampleTypes']);

            $after = $this->snapshot($panel);
            if ($before === null) {
                $this->auditor->record(ConfigurationAuditAction::AstPanelCreated, $panel, after: ['code' => $panel->code, ...$after]);
            } else {
                $changed = array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH);
                $this->auditor->record(ConfigurationAuditAction::AstPanelUpdated, $panel,
                    before: array_intersect_key($before, $changed),
                    after: $changed,
                );
            }

            return $panel;
        });
    }

    private function newCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));
        if ($code === '' || mb_strlen($code) > 30 || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/u', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'El código lleva letras, números, punto o guion (máximo 30).']);
        }
        if (AstPanel::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Ya existe un panel con ese código.']);
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AstPanel $panel): array
    {
        return [
            'name' => $panel->name,
            'is_vitek' => $panel->is_vitek,
            'read_min_hours' => $panel->read_min_hours,
            'read_due_hours' => $panel->read_due_hours,
            'is_active' => $panel->is_active,
            'is_reference_template' => $panel->is_reference_template,
            'organism_groups' => $panel->organism_groups->map(fn (OrganismGroup $g) => $g->value)->values()->all(),
            'sample_types' => $panel->sampleTypes->pluck('code')->sort()->values()->all(),
            'antibiotics' => $panel->antibiotics->pluck('code')->values()->all(),
        ];
    }
}
