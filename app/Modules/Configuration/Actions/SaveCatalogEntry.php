<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\CatalogType;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta o modificación de un ítem de catálogo general (config.catalogs.edit).
 * El código y el grupo de un microorganismo quedan fijos después del alta.
 */
class SaveCatalogEntry
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  array{code?: string, name: string, is_active: bool, organism_group?: string|null, disk_content?: string|null, reported_as_antibiotic_id?: int|null}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, CatalogType $type, ?int $id, array $data, ?int $lockVersion = null): Model
    {
        if (! $user->can(Permission::ConfigCatalogsEdit->value)) {
            throw new AuthorizationException('No tenés permiso para editar catálogos.');
        }

        $name = trim($data['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Ingresá el nombre.']);
        }

        $extra = $type->hasAntibioticFields() ? $this->antibioticFields($id, $data) : [];

        return DB::transaction(function () use ($type, $id, $data, $name, $lockVersion, $extra) {
            $class = $type->modelClass();

            if ($id === null) {
                return $this->create($type, $class, $data, $name, $extra);
            }

            /** @var Model $entry */
            $entry = $class::query()->lockForUpdate()->findOrFail($id);
            if ($lockVersion !== null && $lockVersion !== (int) $entry->getAttribute('lock_version')) {
                throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este ítem. Recargá la página.']);
            }

            $before = ['name' => $entry->getAttribute('name'), 'is_active' => (bool) $entry->getAttribute('is_active')];
            $after = ['name' => $name, 'is_active' => (bool) $data['is_active']];
            foreach (array_keys($extra) as $key) {
                $before[$key] = $entry->getAttribute($key);
            }
            $after = [...$after, ...$extra];
            $changed = array_diff_assoc($after, $before);
            if ($changed === []) {
                return $entry;
            }

            $entry->forceFill([...$after, 'lock_version' => (int) $entry->getAttribute('lock_version') + 1])->save();

            $this->auditor->record(ConfigurationAuditAction::CatalogEntryUpdated, $entry,
                before: array_intersect_key($before, $changed),
                after: $changed,
                context: ['catalog' => $type->value],
            );

            return $entry;
        });
    }

    /**
     * @param  array{disk_content?: string|null, reported_as_antibiotic_id?: int|null}  $data
     * @return array{disk_content: string|null, reported_as_antibiotic_id: int|null}
     *
     * @throws ValidationException
     */
    private function antibioticFields(?int $id, array $data): array
    {
        $disk = filled($data['disk_content'] ?? null) ? trim((string) $data['disk_content']) : null;
        $reportedAs = $data['reported_as_antibiotic_id'] ?? null;
        if ($reportedAs !== null && ($reportedAs === $id || ! Antibiotic::query()->whereKey($reportedAs)->exists())) {
            throw ValidationException::withMessages(['reported_as_antibiotic_id' => 'Elegí otro antibiótico para informar.']);
        }

        return ['disk_content' => $disk, 'reported_as_antibiotic_id' => $reportedAs];
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array{code?: string, name: string, is_active: bool, organism_group?: string|null}  $data
     * @param  array<string, mixed>  $extra
     */
    private function create(CatalogType $type, string $class, array $data, string $name, array $extra = []): Model
    {
        $code = mb_strtoupper(trim($data['code'] ?? ''));
        if ($code === '' || mb_strlen($code) > $type->codeMaxLength() || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/u', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'El código lleva letras, números, punto o guion (máximo '.$type->codeMaxLength().').']);
        }
        if ($class::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Ya existe un ítem con ese código.']);
        }

        $attributes = ['code' => $code, 'name' => $name, 'is_active' => (bool) $data['is_active'], ...$extra];

        if ($type->hasOrganismGroup()) {
            $group = OrganismGroup::tryFrom((string) ($data['organism_group'] ?? ''));
            if ($group === null) {
                throw ValidationException::withMessages(['organism_group' => 'Elegí el grupo del microorganismo.']);
            }
            $attributes['organism_group'] = $group;
        }

        $entry = new $class;
        $entry->forceFill($attributes)->save();

        $this->auditor->record(ConfigurationAuditAction::CatalogEntryCreated, $entry,
            after: [...$attributes, 'organism_group' => isset($attributes['organism_group']) ? $attributes['organism_group']->value : null],
            context: ['catalog' => $type->value],
        );

        return $entry;
    }
}
