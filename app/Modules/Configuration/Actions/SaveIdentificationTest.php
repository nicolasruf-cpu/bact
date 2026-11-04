<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta o modificación de una prueba del catálogo de identificación (revisión de marchas B1,
 * B2, B5). La define el Director técnico responsable: el nombre, para qué se usa, los
 * resultados posibles y la incubación. Los resultados ya registrados guardan su texto, así
 * que editar el catálogo no cambia el historial. El código de un resultado no se quita si ya
 * se usó: se renombra.
 */
class SaveIdentificationTest
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  array{code?: string, name: string, kind: string, incubation_hours: int|null, is_reportable: bool, is_active: bool, result_options: list<array{code: string, label: string}>}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, ?int $id, array $data, ?int $lockVersion = null): IdentificationTest
    {
        if (! $user->can(Permission::CLINICAL_APPROVE)) {
            throw new AuthorizationException('Las pruebas las define el Director técnico responsable.');
        }

        $name = trim($data['name']);
        $kind = IdentificationTestKind::tryFrom($data['kind']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre.']);
        }
        if ($kind === null) {
            throw ValidationException::withMessages(['kind' => 'Elegí para qué se usa la prueba.']);
        }
        $options = [];
        foreach ($data['result_options'] as $i => $option) {
            $label = trim($option['label']);
            $code = mb_strtoupper(trim($option['code'])) ?: mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/u', '', $label) ?? '', 0, 10));
            if ($label === '' || $code === '' || preg_match('/^[A-Z0-9][A-Z0-9._+-]*$/u', $code) !== 1 || mb_strlen($code) > 20) {
                throw ValidationException::withMessages(["result_options.{$i}.label" => 'Cada resultado lleva un texto (y un código de letras y números).']);
            }
            $options[] = ['code' => $code, 'label' => $label];
        }
        if (count($options) < 2) {
            throw ValidationException::withMessages(['result_options' => 'Cargá al menos dos resultados posibles.']);
        }
        if (count(array_unique(array_column($options, 'code'))) !== count($options)) {
            throw ValidationException::withMessages(['result_options' => 'Hay resultados con el mismo código.']);
        }

        return DB::transaction(function () use ($id, $data, $name, $kind, $options, $lockVersion) {
            if ($id === null) {
                $test = new IdentificationTest(['code' => $this->newCode($data['code'] ?? '')]);
                $before = null;
            } else {
                /** @var IdentificationTest $test */
                $test = IdentificationTest::query()->lockForUpdate()->findOrFail($id);
                if ($lockVersion !== null && $lockVersion !== $test->lock_version) {
                    throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó esta prueba. Recargá la página.']);
                }
                $used = DB::table('isolate_tests')->where('identification_test_id', $test->id)->whereNotNull('result_code')->distinct()->pluck('result_code')->all();
                $missing = array_diff($used, array_column($options, 'code'));
                if ($missing !== []) {
                    throw ValidationException::withMessages(['result_options' => 'No se puede quitar un resultado que ya se usó ('.implode(', ', $missing).'): cambiale el texto o desactivá la prueba.']);
                }
                $before = $this->snapshot($test);
            }

            $test->forceFill([
                'name' => $name,
                'kind' => $kind,
                'result_options' => $options,
                'incubation_hours' => $data['incubation_hours'] !== null && $data['incubation_hours'] > 0 ? (int) $data['incubation_hours'] : null,
                'is_reportable' => (bool) $data['is_reportable'],
                'is_active' => (bool) $data['is_active'],
                'is_reference_template' => false,
                'lock_version' => $id === null ? 0 : $test->lock_version + 1,
            ])->save();

            $after = $this->snapshot($test);
            if ($before === null) {
                $this->auditor->record(ConfigurationAuditAction::IdentificationTestCreated, $test, after: ['code' => $test->code, ...$after]);
            } else {
                $changed = array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH);
                $this->auditor->record(ConfigurationAuditAction::IdentificationTestUpdated, $test, before: array_intersect_key($before, $changed), after: $changed);
            }

            return $test;
        });
    }

    private function newCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));
        if ($code === '' || mb_strlen($code) > 30 || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/u', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'El código lleva letras, números, punto o guion (máximo 30).']);
        }
        if (IdentificationTest::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Ya existe una prueba con ese código.']);
        }

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(IdentificationTest $test): array
    {
        return [
            'name' => $test->name,
            'kind' => $test->kind->value,
            'result_options' => $test->result_options,
            'incubation_hours' => $test->incubation_hours,
            'is_reportable' => $test->is_reportable,
            'is_active' => $test->is_active,
            'is_reference_template' => $test->is_reference_template,
        ];
    }
}
