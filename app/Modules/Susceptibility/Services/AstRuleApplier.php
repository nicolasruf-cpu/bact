<?php

namespace App\Modules\Susceptibility\Services;

use App\Modules\Configuration\Models\AstRule;
use App\Modules\Identification\Models\IsolateTest;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Illuminate\Support\Collection;

/**
 * Reglas de antibiograma del laboratorio que valen para un antibiograma (revisión de marchas
 * C2): por el grupo del germen, la muestra y, si la regla lo pide, el resultado de una prueba
 * del aislamiento (p. ej., sinergia BLEE positiva). No trae reglas propias.
 */
class AstRuleApplier
{
    /**
     * @return Collection<int, Collection<int, AstRule>> id del antibiótico => reglas
     */
    public function forTest(SusceptibilityTest $test): Collection
    {
        $test->loadMissing(['isolate.organism', 'isolate.culture.sampleStudy.sample']);
        $group = $test->isolate->organism?->organism_group;
        if ($group === null) {
            return collect();
        }
        $sampleTypeId = $test->isolate->culture->sampleStudy->sample->sample_type_id;

        $readTests = IsolateTest::query()->where('isolate_id', $test->isolate_id)->whereNotNull('read_at')->get();

        $rules = AstRule::query()
            ->where('is_active', true)
            ->whereJsonContains('organism_groups', $group->value)
            ->where(fn ($q) => $q
                ->whereDoesntHave('sampleTypes')
                ->orWhereHas('sampleTypes', fn ($s) => $s->whereKey($sampleTypeId)))
            ->with(['antibiotics', 'test'])
            ->orderBy('name')
            ->get()
            ->filter(fn (AstRule $rule) => $rule->identification_test_id === null
                || $readTests->contains(fn (IsolateTest $t) => $t->identification_test_id === $rule->identification_test_id
                    && $t->result_code === $rule->test_result_code));

        $byAntibiotic = collect();
        foreach ($rules as $rule) {
            foreach ($rule->antibiotics as $antibiotic) {
                $byAntibiotic->put($antibiotic->id, $byAntibiotic->get($antibiotic->id, collect())->push($rule));
            }
        }

        return $byAntibiotic;
    }
}
