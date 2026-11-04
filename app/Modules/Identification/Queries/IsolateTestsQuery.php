<?php

namespace App\Modules\Identification\Queries;

use App\Modules\Configuration\Enums\IdentificationTestKind;
use App\Modules\Configuration\Models\IdentificationTest;
use App\Modules\Identification\Models\IsolateTest;

/**
 * Pruebas del catálogo y pruebas hechas sobre un aislamiento, para las pantallas de
 * identificación y antibiograma y para la reconstrucción de la marcha.
 */
class IsolateTestsQuery
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forIsolate(int $isolateId): array
    {
        return IsolateTest::query()
            ->where('isolate_id', $isolateId)
            ->with('test')
            ->orderBy('set_up_at')
            ->orderBy('id')
            ->get()
            ->map(fn (IsolateTest $t) => [
                'id' => $t->id,
                'code' => $t->test->code,
                'name' => $t->test->name,
                'kind' => $t->test->kind->value,
                'kind_label' => $t->test->kind->label(),
                'options' => $t->test->result_options,
                'set_up_at' => $t->set_up_at->toIso8601String(),
                'readable_at' => $t->readable_at->toIso8601String(),
                'read_at' => $t->read_at?->toIso8601String(),
                'result_code' => $t->result_code,
                'result' => $t->result_label,
                'notes' => $t->notes,
                'is_reportable' => $t->test->is_reportable,
                'for_susceptibility' => $t->susceptibility_test_id !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * Pruebas activas del catálogo, agrupadas por tipo en el orden del enum.
     *
     * @param  list<IdentificationTestKind>|null  $kinds
     * @return array<int, array<string, mixed>>
     */
    public function catalog(?array $kinds = null): array
    {
        $order = array_map(fn (IdentificationTestKind $k) => $k->value, IdentificationTestKind::cases());

        return IdentificationTest::query()
            ->where('is_active', true)
            ->when($kinds !== null, fn ($q) => $q->whereIn('kind', array_map(fn (IdentificationTestKind $k) => $k->value, $kinds ?? [])))
            ->orderBy('name')
            ->get()
            ->sortBy(fn (IdentificationTest $t) => array_search($t->kind->value, $order, true))
            ->map(fn (IdentificationTest $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'kind' => $t->kind->value,
                'kind_label' => $t->kind->label(),
                'options' => $t->result_options,
                'incubation_hours' => $t->incubation_hours,
            ])
            ->values()
            ->all();
    }
}
