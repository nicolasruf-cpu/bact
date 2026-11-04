<?php

namespace App\Modules\Configuration\Enums;

/**
 * Qué hace una regla de antibiograma que carga el laboratorio (revisión de marchas C2).
 * El sistema no trae reglas: las define el Director técnico.
 */
enum AstRuleEffect: string
{
    case Suppress = 'suppress';
    case Comment = 'comment';
    case Interpret = 'interpret';

    public function label(): string
    {
        return match ($this) {
            self::Suppress => 'No informar',
            self::Comment => 'Agregar un comentario al informe',
            self::Interpret => 'Fijar la interpretación (regla experta)',
        };
    }
}
