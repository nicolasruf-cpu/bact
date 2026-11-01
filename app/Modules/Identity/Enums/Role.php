<?php

namespace App\Modules\Identity\Enums;

/**
 * Roles: agrupaciones de permisos. Fuente: docs/functional/ROLES_PERMISSIONS.md.
 * Donde la matriz tiene ❓, se usa el default propuesto entre paréntesis.
 */
enum Role: string
{
    case Admin = 'admin';
    case TechnicalDirector = 'technical_director';
    case Biochemist = 'biochemist';
    case Assistant = 'assistant';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::TechnicalDirector => 'Director técnico',
            self::Biochemist => 'Bioquímico',
            self::Assistant => 'Auxiliar',
            self::Viewer => 'Consulta',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => [
                Permission::SamplesView,
                Permission::ConfigCatalogsEdit,
                Permission::UsersManage,
                Permission::AuditView,
            ],
            self::TechnicalDirector => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission) => $permission !== Permission::UsersManage,
            )),
            self::Biochemist => [
                Permission::SamplesView,
                Permission::PatientsViewIdentity,
                Permission::SamplesReceive,
                Permission::SamplesReject,
                Permission::CulturesInoculate,
                Permission::CulturesReadNegative,
                Permission::CulturesReadPositive,
                Permission::BloodCulturesLoad,
                Permission::BloodCulturesSignalPositive,
                Permission::BloodCulturesConfirmNegative,
                Permission::GramPerform,
                Permission::DirectExamsPerform,
                Permission::IsolatesCreate,
                Permission::IdentificationBiochemSetup,
                Permission::IdentificationBiochemRead,
                Permission::IdentificationPerform,
                Permission::VitekLoad,
                Permission::AstSetup,
                Permission::AstRead,
                Permission::AstInterpretationOverride,
                Permission::ReportsValidate,
                Permission::ReportsValidateOwnWork,
                Permission::CriticalCommunicate,
                Permission::BatchesManageOwn,
                Permission::BatchesTransfer,
                Permission::ConfigClinicalDraft,
                Permission::StatisticsView,
            ],
            self::Assistant => [
                Permission::SamplesView,
                Permission::PatientsViewIdentity,
                Permission::SamplesReceive,
                Permission::SamplesReject,
                Permission::CulturesInoculate,
                // Carga el frasco y registra la alarma de positivo del equipo; no informa negativos (Q-S1).
                Permission::BloodCulturesLoad,
                Permission::BloodCulturesSignalPositive,
                Permission::IdentificationBiochemSetup,
                Permission::IdentificationBiochemRead,
                Permission::VitekLoad,
                Permission::BatchesManageOwn,
            ],
            self::Viewer => [
                Permission::SamplesView,
                Permission::PatientsViewIdentity,
                Permission::StatisticsView,
            ],
        };
    }
}
