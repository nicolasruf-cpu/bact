<?php

namespace App\Modules\Identification\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum IdentificationAuditAction: string implements AuditAction
{
    case Identified = 'isolate.identified';
    case TestsRecorded = 'isolate.tests_recorded';
    case TestsRead = 'isolate.tests_read';
    case InstrumentLoaded = 'isolate.instrument_loaded';
    case Inconclusive = 'isolate.identification_inconclusive';
    case SignificanceChanged = 'isolate.significance_changed';
    case Subcultured = 'isolate.subcultured';
    case Referred = 'isolate.referred';
    case ReferralResultRecorded = 'isolate.referral_result';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Identified => 'Identificó un aislamiento',
            self::TestsRecorded => 'Registró pruebas de un aislamiento',
            self::TestsRead => 'Leyó pruebas de un aislamiento',
            self::InstrumentLoaded => 'Cargó una tarjeta en el equipo',
            self::Inconclusive => 'Registró una identificación no concluyente',
            self::SignificanceChanged => 'Cambió la significancia de un aislamiento',
            self::Subcultured => 'Repicó un aislamiento',
            self::Referred => 'Derivó un aislamiento',
            self::ReferralResultRecorded => 'Registró el resultado de una derivación',
        };
    }
}
