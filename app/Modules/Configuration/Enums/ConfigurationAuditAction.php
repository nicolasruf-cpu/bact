<?php

namespace App\Modules\Configuration\Enums;

use App\Modules\Audit\Contracts\AuditAction;

enum ConfigurationAuditAction: string implements AuditAction
{
    case CatalogEntryCreated = 'configuration.catalog_entry_created';
    case CatalogEntryUpdated = 'configuration.catalog_entry_updated';
    case ProtocolCreated = 'configuration.protocol_created';
    case ProtocolVersionDrafted = 'configuration.protocol_version_drafted';
    case ProtocolVersionUpdated = 'configuration.protocol_version_updated';
    case ProtocolVersionApproved = 'configuration.protocol_version_approved';
    case ProtocolVersionActivated = 'configuration.protocol_version_activated';
    case ProtocolVersionRetired = 'configuration.protocol_version_retired';
    case BreakpointSetDrafted = 'configuration.breakpoint_set_drafted';
    case BreakpointsImported = 'configuration.breakpoints_imported';
    case BreakpointSetApproved = 'configuration.breakpoint_set_approved';
    case BreakpointSetActivated = 'configuration.breakpoint_set_activated';
    case BreakpointSetRetired = 'configuration.breakpoint_set_retired';

    public function auditName(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::CatalogEntryCreated => 'Alta en un catálogo',
            self::CatalogEntryUpdated => 'Modificación en un catálogo',
            self::ProtocolCreated => 'Protocolo nuevo',
            self::ProtocolVersionDrafted => 'Borrador de versión de protocolo',
            self::ProtocolVersionUpdated => 'Modificación de un borrador de protocolo',
            self::ProtocolVersionApproved => 'Aprobación de versión de protocolo',
            self::ProtocolVersionActivated => 'Versión de protocolo vigente',
            self::ProtocolVersionRetired => 'Versión de protocolo retirada',
            self::BreakpointSetDrafted => 'Borrador de puntos de corte',
            self::BreakpointsImported => 'Importación de puntos de corte',
            self::BreakpointSetApproved => 'Aprobación de puntos de corte',
            self::BreakpointSetActivated => 'Puntos de corte vigentes',
            self::BreakpointSetRetired => 'Puntos de corte retirados',
        };
    }
}
