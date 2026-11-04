<?php

namespace App\Modules\Cultures\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum CultureAuditAction: string implements AuditAction
{
    case Inoculated = 'culture.inoculated';
    case Read = 'culture.read';
    case Completed = 'culture.completed';
    case EnrichmentSubcultured = 'culture.enrichment_subcultured';
    case DirectExamRecorded = 'direct_exam.recorded';
    case PlateLost = 'culture.plate_lost';
    case SampleRejected = 'sample_study.rejected';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Inoculated => 'Sembró un cultivo',
            self::Read => 'Leyó un cultivo',
            self::Completed => 'Cerró un cultivo',
            self::EnrichmentSubcultured => 'Repicó un caldo de enriquecimiento',
            self::DirectExamRecorded => 'Registró un examen directo',
            self::PlateLost => 'Dio por perdida una placa',
            self::SampleRejected => 'Declaró una muestra no apta',
        };
    }
}
