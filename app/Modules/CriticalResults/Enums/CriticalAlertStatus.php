<?php

namespace App\Modules\CriticalResults\Enums;

/**
 * STATE_MACHINES.md (CriticalAlert). El escalamiento por plazo queda para cuando
 * el laboratorio defina plazos (Q-C5).
 */
enum CriticalAlertStatus: string
{
    case Open = 'open';
    case Communicated = 'communicated';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Sin comunicar',
            self::Communicated => 'Comunicado',
        };
    }
}
