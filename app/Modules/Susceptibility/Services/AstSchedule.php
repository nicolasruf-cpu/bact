<?php

namespace App\Modules\Susceptibility\Services;

use App\Modules\Susceptibility\Models\SusceptibilityResult;
use App\Modules\Susceptibility\Models\SusceptibilityTest;
use Carbon\CarbonInterface;

/**
 * Cuándo se lee un antibiograma (revisión de marchas A2): el panel puede tener su propia
 * incubación (el VITEK termina en horas; la difusión necesita la noche). Sin ella, y para los
 * antibióticos agregados por fuera del panel, vale la del protocolo.
 */
class AstSchedule
{
    /**
     * @return array{0: int, 1: int|null} horas mínimas y de vencimiento
     */
    public function hours(SusceptibilityTest $test, bool $additional = false): array
    {
        $test->loadMissing(['panel', 'isolate.culture.sampleStudy.protocolVersion']);
        $version = $test->isolate->culture->sampleStudy->protocolVersion;

        if (! $additional && $test->panel->read_min_hours !== null) {
            return [$test->panel->read_min_hours, $test->panel->read_due_hours];
        }

        return [(int) $version->ast_read_min_hours, $version->ast_read_due_hours];
    }

    /**
     * Desde cuándo se pueden leer los antibióticos que faltan: el último que se cumple.
     */
    public function availableAt(SusceptibilityTest $test): ?CarbonInterface
    {
        $test->loadMissing('results');

        return $test->pendingResults()
            ->map(fn (SusceptibilityResult $r) => $r->set_up_at->addHours($this->hours($test, $r->is_additional)[0]))
            ->sortBy(fn (CarbonInterface $at) => $at->getTimestamp())
            ->last();
    }
}
