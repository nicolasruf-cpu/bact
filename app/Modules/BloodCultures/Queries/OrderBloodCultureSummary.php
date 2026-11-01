<?php

namespace App\Modules\BloodCultures\Queries;

use App\Modules\BloodCultures\Enums\BottleStatus;
use App\Modules\BloodCultures\Models\BloodCultureBottle;
use App\Modules\Configuration\Enums\ProcessingKind;
use App\Modules\Samples\Models\SampleStudy;

/**
 * Hemocultivos y retrocultivos de una orden, contados por separado por estudio
 * ("Hemocultivos: 1 de 2 positivos · Retrocultivo: 0 de 1"). Cada frasco se sigue por
 * su cuenta; esto solo los muestra juntos. No interpreta (p. ej., no calcula el tiempo
 * diferencial de positividad: Q-C10).
 */
class OrderBloodCultureSummary
{
    /**
     * @return array<int, array{study: string, total: int, positive: int, negative: int, incubating: int, pending: int, text: string, bottles: array<int, array<string, mixed>>}>
     */
    public function forOrder(int $labOrderId): array
    {
        $studies = SampleStudy::query()
            ->whereHas('sample', fn ($q) => $q->where('lab_order_id', $labOrderId))
            ->whereHas('protocolVersion', fn ($q) => $q->where('processing_kind', ProcessingKind::BloodBottle->value))
            ->with(['study', 'sample', 'protocolVersion'])
            ->orderBy('id')
            ->get();

        $bottles = BloodCultureBottle::query()
            ->whereIn('sample_study_id', $studies->pluck('id'))
            ->with('gramStain')
            ->get()
            ->keyBy('sample_study_id');

        return $studies
            ->groupBy('study_id')
            ->map(function ($group) use ($bottles) {
                $rows = $group->map(function (SampleStudy $study) use ($bottles) {
                    /** @var BloodCultureBottle|null $bottle */
                    $bottle = $bottles->get($study->id);
                    $settings = $study->protocolVersion->bloodCultureSettings();

                    return [
                        'sample_study_id' => $study->id,
                        'barcode' => $study->sample->barcode,
                        'status' => $bottle?->status->value ?? 'pending',
                        'status_label' => $bottle?->status->label() ?? 'Sin cargar',
                        'positive_at' => $bottle?->positive_at?->toIso8601String(),
                        'minutes_to_positivity' => $bottle?->minutesToPositivity(),
                        'gram' => $bottle?->gramStain && $settings ? $settings->gramLabel($bottle->gramStain->option_code) : null,
                    ];
                })->values();

                $count = fn (string $status) => $rows->where('status', $status)->count();
                $total = $rows->count();
                $positive = $count(BottleStatus::Positive->value);
                $name = $group->first()->study->name;

                return [
                    'study' => $name,
                    'total' => $total,
                    'positive' => $positive,
                    'negative' => $count(BottleStatus::Negative->value),
                    'incubating' => $count(BottleStatus::Incubating->value),
                    'pending' => $count('pending'),
                    'text' => "{$name}: {$positive} de {$total} positivo".($total === 1 ? '' : 's'),
                    'bottles' => $rows->all(),
                ];
            })
            ->values()
            ->all();
    }
}
