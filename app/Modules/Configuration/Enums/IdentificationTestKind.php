<?php

namespace App\Modules\Configuration\Enums;

/**
 * Para qué se usa una prueba del catálogo de identificación. Ordena la pantalla y el
 * informe; no cambia qué hace el sistema con el resultado.
 */
enum IdentificationTestKind: string
{
    case Gram = 'gram';
    case Rapid = 'rapid';
    case Biochemical = 'biochemical';
    case Complementary = 'complementary';
    case Mechanism = 'mechanism';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'Gram de la colonia',
            self::Rapid => 'Prueba rápida',
            self::Biochemical => 'Bioquímica',
            self::Complementary => 'Complementaria',
            self::Mechanism => 'Mecanismo de resistencia',
        };
    }
}
