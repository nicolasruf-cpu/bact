<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Models\Breakpoint;

/**
 * Interpretación automática a partir de los puntos de corte cargados por el laboratorio.
 * No contiene ningún valor clínico: solo la regla de comparación.
 * - Difusión: S si halo ≥ S; R si halo ≤ R; si no, I.
 * - CIM:     S si CIM ≤ S; R si CIM ≥ R; si no, I.
 * Un operador ">" en la CIM (fuera de rango superior) se interpreta con el valor informado.
 */
class BreakpointInterpreter
{
    public function interpret(?Breakpoint $breakpoint, ?float $value): ?Interpretation
    {
        if ($breakpoint === null || $value === null) {
            return null;
        }

        $susceptible = (float) $breakpoint->susceptible_value;
        $resistant = (float) $breakpoint->resistant_value;

        return match ($breakpoint->method) {
            AstMethod::Disk => match (true) {
                $value >= $susceptible => Interpretation::Susceptible,
                $value <= $resistant => Interpretation::Resistant,
                default => Interpretation::Intermediate,
            },
            AstMethod::Mic => match (true) {
                $value <= $susceptible => Interpretation::Susceptible,
                $value >= $resistant => Interpretation::Resistant,
                default => Interpretation::Intermediate,
            },
        };
    }
}
