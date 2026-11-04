<?php

namespace App\Modules\Cultures\Services;

use App\Modules\Configuration\Enums\ReadingOutcome;
use App\Modules\Cultures\Enums\PlateStatus;
use App\Modules\Cultures\Models\Culture;
use App\Modules\Cultures\Models\Plate;
use App\Modules\Cultures\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cuándo se puede leer un cultivo, según la versión de protocolo congelada.
 * Cronograma: cada lectura programada se cuenta desde la siembra (p. ej., 24, 48 y 72 h);
 * se hacen todas, aunque una intermedia tenga desarrollo (D-0017). Después de reincubar:
 * última lectura + reincubación. Las placas repicadas de un caldo necesitan, además,
 * la incubación mínima de la primera lectura desde su propio repique; lo mismo una placa
 * resembrada porque se perdió la original (B7).
 */
class ReadingSchedule
{
    /**
     * Lectura programada que toca (0 = la primera).
     */
    public function step(Culture $culture): int
    {
        return min($this->done($culture), $this->steps($culture) - 1);
    }

    /**
     * Lecturas programadas ya hechas (con o sin desarrollo; las reincubaciones no cuentan).
     */
    public function done(Culture $culture): int
    {
        return $culture->readings
            ->filter(fn (Reading $r) => in_array($r->outcome, [ReadingOutcome::NoGrowth, ReadingOutcome::Growth], true))
            ->count();
    }

    /**
     * Ya se hicieron todas las lecturas del cronograma.
     */
    public function isComplete(Culture $culture): bool
    {
        return $this->done($culture) >= $this->steps($culture);
    }

    public function steps(Culture $culture): int
    {
        return count($culture->sampleStudy->protocolVersion->readingSchedule());
    }

    public function isLastStep(Culture $culture): bool
    {
        return $this->step($culture) >= $this->steps($culture) - 1;
    }

    /**
     * Horas desde la siembra de la lectura programada que toca (para mostrar "lectura de las 48 h").
     */
    public function stepHours(Culture $culture): int
    {
        return $culture->sampleStudy->protocolVersion->readingSchedule()[$this->step($culture)]['min_hours'];
    }

    public function nextReadingAvailableAt(Culture $culture): CarbonInterface
    {
        $version = $culture->sampleStudy->protocolVersion;
        /** @var Reading|null $last */
        $last = $culture->readings->last();

        if ($last !== null && $last->outcome === ReadingOutcome::Reincubate) {
            return $last->read_at->copy()->addHours((int) $version->reincubation_hours);
        }

        $availableAt = $culture->inoculated_at->copy()->addHours($version->readingSchedule()[$this->step($culture)]['min_hours']);

        /** @var Plate|null $latestSubculture */
        $latestSubculture = $culture->plates->filter(fn (Plate $p) => ($p->source_plate_id !== null || $p->replaces_plate_id !== null) && $p->inoculated_at !== null && $p->status !== PlateStatus::Lost)
            ->sortBy('inoculated_at')
            ->last();
        if ($latestSubculture !== null) {
            $subcultureReady = $latestSubculture->inoculated_at?->copy()->addHours($version->readingSchedule()[0]['min_hours']);
            if ($subcultureReady !== null && $subcultureReady->greaterThan($availableAt)) {
                return $subcultureReady;
            }
        }

        return $availableAt;
    }

    /**
     * Desde cuándo y hasta cuándo se hace la lectura programada siguiente a la actual.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function followingStep(Culture $culture): array
    {
        $step = $culture->sampleStudy->protocolVersion->readingSchedule()[$this->step($culture) + 1];

        return [
            $culture->inoculated_at->copy()->addHours($step['min_hours']),
            $culture->inoculated_at->copy()->addHours($step['due_hours']),
        ];
    }

    /**
     * Caldos de enriquecimiento todavía sin repicar.
     *
     * @return Collection<int, Plate>
     */
    public function pendingEnrichments(Culture $culture): Collection
    {
        return $culture->plates
            ->filter(fn (Plate $p) => $p->is_enrichment && $p->status === PlateStatus::Incubating)
            ->values()
            ->toBase();
    }
}
