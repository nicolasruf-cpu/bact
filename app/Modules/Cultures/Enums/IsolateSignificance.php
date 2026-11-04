<?php

namespace App\Modules\Cultures\Enums;

/**
 * Significancia de un aislamiento (revisión de marchas A4). La decide el bacteriólogo al
 * cargarlo y la puede cambiar después, con motivo: muchas veces se decide al identificarlo
 * (p. ej., un estafilococo coagulasa negativo en uno de dos hemocultivos).
 */
enum IsolateSignificance: string
{
    case Significant = 'significant';
    case ProbableContaminant = 'probable_contaminant';
    case NotSignificant = 'not_significant';

    public function label(): string
    {
        return match ($this) {
            self::Significant => 'Significativo',
            self::ProbableContaminant => 'Probable contaminante',
            self::NotSignificant => 'No significativo',
        };
    }

    public function isSignificant(): bool
    {
        return $this === self::Significant;
    }
}
