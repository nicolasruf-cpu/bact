<?php

namespace App\Modules\Configuration\Enums;

/**
 * Grupos de organismos: definen el panel de antibiograma y los puntos de corte aplicables.
 */
enum OrganismGroup: string
{
    case Enterobacterales = 'enterobacterales';
    case Pseudomonas = 'pseudomonas';
    case Acinetobacter = 'acinetobacter';
    case Enterococcus = 'enterococcus';
    case Staphylococcus = 'staphylococcus';
    case Streptococcus = 'streptococcus';
    case Yeast = 'yeast';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Enterobacterales => 'Enterobacterales',
            self::Pseudomonas => 'Pseudomonas',
            self::Acinetobacter => 'Acinetobacter',
            self::Enterococcus => 'Enterococcus',
            self::Staphylococcus => 'Staphylococcus',
            self::Streptococcus => 'Streptococcus',
            self::Yeast => 'Levaduras',
            self::Other => 'Otros',
        };
    }
}
