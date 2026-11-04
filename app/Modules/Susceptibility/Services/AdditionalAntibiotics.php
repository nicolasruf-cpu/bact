<?php

namespace App\Modules\Susceptibility\Services;

use App\Models\User;
use App\Modules\Audit\Services\Auditor;
use App\Modules\Configuration\Models\Antibiotic;
use App\Modules\Susceptibility\Data\AdditionalAntibiotic;
use App\Modules\Susceptibility\Enums\SusceptibilityAuditAction;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Agrega al antibiograma antibióticos por fuera del panel (D-0022), cada uno con su método.
 * Se llama dentro de la transacción de la preparación o de la lectura.
 */
class AdditionalAntibiotics
{
    public function __construct(private readonly Auditor $auditor) {}

    /**
     * @param  list<AdditionalAntibiotic>  $additional
     *
     * @throws ValidationException
     */
    public function add(User $user, SusceptibilityTest $test, array $additional, CarbonInterface $at): void
    {
        if ($additional === []) {
            return;
        }

        $ids = array_map(fn (AdditionalAntibiotic $a) => $a->antibioticId, $additional);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['additional' => 'Hay un antibiótico agregado dos veces.']);
        }
        $existing = $test->results()->pluck('antibiotic_id')->all();
        if (array_intersect($ids, $existing) !== []) {
            throw ValidationException::withMessages(['additional' => 'Hay un antibiótico que ya está en el antibiograma.']);
        }
        $antibiotics = Antibiotic::query()->whereIn('id', $ids)->where('is_active', true)->pluck('code', 'id');
        if ($antibiotics->count() !== count($ids)) {
            throw ValidationException::withMessages(['additional' => 'Hay un antibiótico que no existe o está inactivo.']);
        }

        foreach ($additional as $item) {
            $test->results()->create([
                'antibiotic_id' => $item->antibioticId,
                'method' => $item->method,
                'is_additional' => true,
                'addition_reason' => filled($item->reason) ? trim((string) $item->reason) : null,
                'set_up_at' => $at,
                'set_up_by' => $user->id,
                'is_reported' => true,
            ]);
        }

        $this->auditor->record(SusceptibilityAuditAction::AntibioticsAdded, $test, after: [
            'antibiotics' => array_map(fn (AdditionalAntibiotic $a) => [
                'antibiotic' => $antibiotics[$a->antibioticId],
                'method' => $a->method->value,
                'reason' => $a->reason,
            ], $additional),
        ]);
    }
}
