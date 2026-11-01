<?php

namespace App\Modules\Samples\Enums;

enum SampleStudyStatus: string
{
    case Pending = 'pending';
    case InProcess = 'in_process';
    case PreliminaryReported = 'preliminary_reported';
    case ReadyForValidation = 'ready_for_validation';
    case FinalReported = 'final_reported';
    case InCorrection = 'in_correction';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de siembra',
            self::InProcess => 'En proceso',
            self::PreliminaryReported => 'Informe preliminar',
            self::ReadyForValidation => 'Lista para validar',
            self::FinalReported => 'Informe definitivo',
            self::InCorrection => 'En corrección',
            self::Cancelled => 'Anulado',
        };
    }
}
