<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\AstRule;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta o modificación de una regla de antibiograma (revisión de marchas C2): informe
 * selectivo, comentario o regla experta. La define el Director técnico responsable; vale para
 * los antibiogramas que se lean desde ahora. El sistema no trae reglas propias.
 */
class SaveAstRule
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  array{code?: string, name: string, effect: string, interpretation: string|null, comment: string|null, organism_groups: list<string>, sample_type_ids: list<int>, antibiotic_ids: list<int>, identification_test_id: int|null, test_result_code: string|null, is_active: bool}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ?int $id, array $data, ?int $lockVersion = null): AstRule
    {
        if (! $user->can(Permission::CLINICAL_APPROVE)) {
            throw new AuthorizationException('Las reglas las define el Director técnico responsable.');
        }

        $name = trim($data['name']);
        $effect = AstRuleEffect::tryFrom($data['effect']);
        $comment = filled($data['comment']) ? trim((string) $data['comment']) : null;
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre.']);
        }
        if ($effect === null) {
            throw ValidationException::withMessages(['effect' => 'Elegí qué hace la regla.']);
        }
        $interpretation = $effect === AstRuleEffect::Interpret ? Interpretation::tryFrom((string) $data['interpretation']) : null;
        if ($effect === AstRuleEffect::Interpret && $interpretation === null) {
            throw ValidationException::withMessages(['interpretation' => 'Elegí la interpretación que fija la regla.']);
        }
        if ($effect === AstRuleEffect::Comment && $comment === null) {
            throw ValidationException::withMessages(['comment' => 'Escribí el comentario que sale en el informe.']);
        }

        $groups = [];
        foreach (array_unique($data['organism_groups']) as $value) {
            $group = OrganismGroup::tryFrom($value) ?? throw ValidationException::withMessages(['organism_groups' => 'Hay un grupo de germen que no existe.']);
            $groups[] = $group;
        }
        if ($groups === []) {
            throw ValidationException::withMessages(['organism_groups' => 'Elegí al menos un grupo de germen.']);
        }
        $antibioticIds = array_values(array_unique($data['antibiotic_ids']));
        if ($antibioticIds === [] || Antibiotic::query()->whereIn('id', $antibioticIds)->count() !== count($antibioticIds)) {
            throw ValidationException::withMessages(['antibiotic_ids' => 'Elegí al menos un antibiótico.']);
        }
        $sampleTypeIds = array_values(array_unique($data['sample_type_ids']));
        if (SampleType::query()->whereIn('id', $sampleTypeIds)->count() !== count($sampleTypeIds)) {
            throw ValidationException::withMessages(['sample_type_ids' => 'Hay un tipo de muestra que no existe.']);
        }
        $testId = $data['identification_test_id'];
        $resultCode = $testId === null ? null : $data['test_result_code'];
        if ($testId !== null) {
            $test = IdentificationTest::query()->find($testId);
            if ($test === null || $resultCode === null || $test->resultLabel($resultCode) === null) {
                throw ValidationException::withMessages(['test_result_code' => 'Elegí la prueba y el resultado que activa la regla.']);
            }
        }

        return DB::transaction(function () use ($id, $data, $name, $effect, $interpretation, $comment, $groups, $antibioticIds, $sampleTypeIds, $testId, $resultCode, $lockVersion) {
            if ($id === null) {
                $code = mb_strtoupper(trim($data['code'] ?? ''));
                if ($code === '' || mb_strlen($code) > 30 || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/u', $code) !== 1) {
                    throw ValidationException::withMessages(['code' => 'El código lleva letras, números, punto o guion (máximo 30).']);
                }
                if (AstRule::query()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages(['code' => 'Ya existe una regla con ese código.']);
                }
                $rule = new AstRule(['code' => $code]);
                $before = null;
            } else {
                /** @var AstRule $rule */
                $rule = AstRule::query()->lockForUpdate()->with(['antibiotics', 'sampleTypes'])->findOrFail($id);
                if ($lockVersion !== null && $lockVersion !== $rule->lock_version) {
                    throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó esta regla. Recargá la página.']);
                }
                $before = $this->snapshot($rule);
            }

            $rule->forceFill([
                'name' => $name,
                'effect' => $effect,
                'interpretation' => $interpretation,
                'comment' => $comment,
                'organism_groups' => $groups,
                'identification_test_id' => $testId,
                'test_result_code' => $resultCode,
                'is_active' => (bool) $data['is_active'],
                'is_reference_template' => false,
                'lock_version' => $id === null ? 0 : $rule->lock_version + 1,
            ])->save();
            $rule->antibiotics()->sync($antibioticIds);
            $rule->sampleTypes()->sync($sampleTypeIds);
            $rule->load(['antibiotics', 'sampleTypes']);

            $after = $this->snapshot($rule);
            if ($before === null) {
                $this->auditor->record(ConfigurationAuditAction::AstRuleCreated, $rule, after: ['code' => $rule->code, ...$after]);
            } else {
                $changed = array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH);
                $this->auditor->record(ConfigurationAuditAction::AstRuleUpdated, $rule, before: array_intersect_key($before, $changed), after: $changed);
            }

            return $rule;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AstRule $rule): array
    {
        return [
            'name' => $rule->name,
            'effect' => $rule->effect->value,
            'interpretation' => $rule->interpretation?->value,
            'comment' => $rule->comment,
            'organism_groups' => $rule->organism_groups->map(fn (OrganismGroup $g) => $g->value)->values()->all(),
            'sample_types' => $rule->sampleTypes->pluck('code')->sort()->values()->all(),
            'antibiotics' => $rule->antibiotics->pluck('code')->sort()->values()->all(),
            'identification_test_id' => $rule->identification_test_id,
            'test_result_code' => $rule->test_result_code,
            'is_active' => $rule->is_active,
        ];
    }
}
