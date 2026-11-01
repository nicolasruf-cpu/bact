<?php

namespace App\Modules\Statistics\Queries;

use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Reporting\Enums\ReportStatus;
use App\Modules\Samples\Enums\SampleStudyStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Perfil de sensibilidad acumulado (antibiograma acumulado).
 * Metodología de referencia (CLSI M39, a validar por el laboratorio — Q-C9):
 * - solo estudios con informe definitivo emitido;
 * - primer aislamiento de cada especie por paciente en el período;
 * - solo resultados informados; se usa la interpretación final (revisada o automática);
 * - se advierte cuando hay menos de 30 aislamientos.
 */
class SusceptibilityProfileQuery
{
    public const MIN_ISOLATES = 30;

    /**
     * @return array{organisms: list<array<string, mixed>>, total_isolates: int}
     */
    public function profile(int $siteId, CarbonInterface $from, CarbonInterface $to): array
    {
        // Un aislamiento por paciente y especie: el más antiguo del período.
        $firstIsolates = DB::table('isolates')
            ->join('cultures', 'cultures.id', '=', 'isolates.culture_id')
            ->join('sample_studies', 'sample_studies.id', '=', 'cultures.sample_study_id')
            ->join('samples', 'samples.id', '=', 'sample_studies.sample_id')
            ->join('lab_orders', 'lab_orders.id', '=', 'samples.lab_order_id')
            ->join('reports', fn ($j) => $j->on('reports.sample_study_id', '=', 'sample_studies.id')->where('reports.status', ReportStatus::Issued->value))
            ->where('sample_studies.status', SampleStudyStatus::FinalReported->value)
            ->where('samples.origin_site_id', $siteId)
            ->where('isolates.is_significant', true)
            ->whereNotNull('isolates.organism_id')
            ->whereBetween('reports.validated_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])
            ->groupBy('lab_orders.patient_id', 'isolates.organism_id')
            ->selectRaw('MIN(isolates.id) AS isolate_id');

        $rows = DB::table('susceptibility_results')
            ->join('susceptibility_tests', 'susceptibility_tests.id', '=', 'susceptibility_results.susceptibility_test_id')
            ->join('isolates', 'isolates.id', '=', 'susceptibility_tests.isolate_id')
            ->joinSub($firstIsolates, 'firsts', 'firsts.isolate_id', '=', 'isolates.id')
            ->join('organisms', 'organisms.id', '=', 'isolates.organism_id')
            ->join('antibiotics', 'antibiotics.id', '=', 'susceptibility_results.antibiotic_id')
            ->where('susceptibility_results.is_reported', true)
            ->selectRaw('organisms.id AS organism_id, organisms.name AS organism, antibiotics.code, antibiotics.name AS antibiotic,
                COALESCE(susceptibility_results.reviewed_interpretation, susceptibility_results.automatic_interpretation) AS interpretation,
                COUNT(*) AS total')
            ->groupBy('organisms.id', 'organisms.name', 'antibiotics.code', 'antibiotics.name', 'interpretation')
            ->orderBy('organisms.name')
            ->get();

        $isolatesPerOrganism = DB::table('isolates')
            ->joinSub($firstIsolates, 'firsts', 'firsts.isolate_id', '=', 'isolates.id')
            ->join('organisms', 'organisms.id', '=', 'isolates.organism_id')
            ->groupBy('organisms.id')
            ->selectRaw('organisms.id, COUNT(*) AS n')
            ->pluck('n', 'id');

        $organisms = [];
        foreach ($rows->groupBy('organism_id') as $organismId => $organismRows) {
            $antibiotics = [];
            foreach ($organismRows->groupBy('code') as $code => $results) {
                $tested = (int) $results->sum('total');
                $count = fn (Interpretation $i) => (int) $results->where('interpretation', $i->value)->sum('total');
                $antibiotics[] = [
                    'code' => $code,
                    'name' => $results->first()->antibiotic,
                    'tested' => $tested,
                    'susceptible' => $count(Interpretation::Susceptible),
                    'intermediate' => $count(Interpretation::Intermediate),
                    'resistant' => $count(Interpretation::Resistant),
                    'percent_susceptible' => $tested > 0 ? round(100 * $count(Interpretation::Susceptible) / $tested) : null,
                ];
            }

            $n = (int) ($isolatesPerOrganism[$organismId] ?? 0);
            $organisms[] = [
                'organism' => $organismRows->first()->organism,
                'isolates' => $n,
                'insufficient' => $n < self::MIN_ISOLATES,
                'antibiotics' => $antibiotics,
            ];
        }

        return ['organisms' => $organisms, 'total_isolates' => (int) $isolatesPerOrganism->sum()];
    }
}
