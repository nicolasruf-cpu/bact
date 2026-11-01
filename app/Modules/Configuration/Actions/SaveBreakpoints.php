<?php

namespace App\Modules\Configuration\Actions;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\ConfigurationAuditAction;
use App\Modules\Configuration\Enums\OrganismGroup;
use App\Modules\Configuration\Enums\ProtocolVersionStatus;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Configuration\Models\BreakpointSet;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reemplaza los puntos de corte de un borrador (importación CSV o carga en la tabla).
 * Valida cada fila y no guarda nada si alguna está mal. Solo controla la forma
 * (grupo, antibiótico, método, números y el sentido S/R de cada método): los valores
 * son los de la edición del M100 que carga el laboratorio.
 */
class SaveBreakpoints
{
    private const MAX_ERRORS = 30;

    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  list<array{line?: int, organism_group: string, antibiotic_code: string, method: string, susceptible: string|int|float, resistant: string|int|float}>  $rows
     * @param  string  $origin  'csv' o 'manual'
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $user, BreakpointSet $set, array $rows, int $lockVersion, string $origin, ?string $fileHash = null): BreakpointSet
    {
        if (! $user->can(Permission::ConfigClinicalDraft->value)) {
            throw new AuthorizationException('No tenés permiso para preparar configuración clínica.');
        }

        return DB::transaction(function () use ($set, $rows, $lockVersion, $origin, $fileHash) {
            /** @var BreakpointSet $set */
            $set = BreakpointSet::query()->lockForUpdate()->findOrFail($set->id);

            if ($set->is_synthetic) {
                throw ValidationException::withMessages(['file' => 'El set de demostración no se modifica.']);
            }
            if ($set->status !== ProtocolVersionStatus::Draft) {
                throw ValidationException::withMessages(['file' => 'Solo se cargan valores en un borrador.']);
            }
            if ($set->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => 'Otra persona modificó este set. Recargá la página.']);
            }

            $normalized = $this->normalize($rows);
            $before = $set->breakpoints()->count();

            $set->breakpoints()->delete();
            foreach ($normalized as $row) {
                $set->breakpoints()->create($row);
            }
            $set->update(['lock_version' => $set->lock_version + 1]);

            $this->auditor->record(ConfigurationAuditAction::BreakpointsImported, $set,
                before: ['breakpoints' => $before],
                after: ['breakpoints' => count($normalized)],
                context: ['origin' => $origin, 'file_sha256' => $fileHash],
            );

            return $set;
        });
    }

    /**
     * @param  list<array{line?: int, organism_group: string, antibiotic_code: string, method: string, susceptible: string|int|float, resistant: string|int|float}>  $rows
     * @return list<array{organism_group: OrganismGroup, antibiotic_id: int, method: AstMethod, susceptible_value: float, resistant_value: float}>
     *
     * @throws ValidationException
     */
    private function normalize(array $rows): array
    {
        $antibiotics = Antibiotic::query()->pluck('id', 'code');
        $errors = [];
        $normalized = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $where = isset($row['line']) ? "Línea {$row['line']}" : 'Fila '.($index + 1);

            $group = OrganismGroup::tryFrom(mb_strtolower(trim($row['organism_group'])));
            $method = AstMethod::tryFrom(mb_strtolower(trim($row['method'])));
            $code = mb_strtoupper(trim($row['antibiotic_code']));
            $antibioticId = $antibiotics[$code] ?? null;
            $s = $this->number($row['susceptible']);
            $r = $this->number($row['resistant']);

            $problem = match (true) {
                $group === null => "grupo \"{$row['organism_group']}\" desconocido",
                $antibioticId === null => "antibiótico \"{$row['antibiotic_code']}\" no está en el catálogo",
                $method === null => "método \"{$row['method']}\" desconocido (disk o mic)",
                $s === null || $r === null => 'los valores S y R tienen que ser números positivos',
                $method === AstMethod::Disk && $s <= $r => 'en difusión, el halo S tiene que ser mayor que el R',
                $method === AstMethod::Mic && $s >= $r => 'en CIM, el valor S tiene que ser menor que el R',
                default => null,
            };

            if ($problem === null) {
                $key = $group->value.'|'.$antibioticId.'|'.$method->value;
                if (isset($seen[$key])) {
                    $problem = 'fila repetida (mismo grupo, antibiótico y método)';
                }
                $seen[$key] = true;
            }

            if ($problem !== null) {
                $errors[] = "{$where}: {$problem}.";
                if (count($errors) >= self::MAX_ERRORS) {
                    break;
                }

                continue;
            }

            /** @var OrganismGroup $group */
            /** @var AstMethod $method */
            $normalized[] = [
                'organism_group' => $group,
                'antibiotic_id' => (int) $antibioticId,
                'method' => $method,
                'susceptible_value' => (float) $s,
                'resistant_value' => (float) $r,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        return $normalized;
    }

    private function number(string|int|float $value): ?float
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || ! is_numeric($text) || (float) $text <= 0 || (float) $text >= 100000) {
            return null;
        }

        return round((float) $text, 3);
    }
}
