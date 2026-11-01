<?php

namespace App\Modules\Identity\Enums;

/**
 * Permisos granulares. Fuente: docs/functional/ROLES_PERMISSIONS.md.
 * Se asignan por sede (spatie teams, ADR-0008).
 */
enum Permission: string
{
    case SamplesView = 'samples.view';
    case PatientsViewIdentity = 'patients.view_identity';
    case SamplesReceive = 'samples.receive';
    case SamplesReject = 'samples.reject';
    case CulturesInoculate = 'cultures.inoculate';
    case CulturesReadNegative = 'cultures.read_negative';
    case CulturesReadPositive = 'cultures.read_positive';
    case BloodCulturesLoad = 'blood_cultures.load';
    case BloodCulturesSignalPositive = 'blood_cultures.signal_positive';
    case BloodCulturesConfirmNegative = 'blood_cultures.confirm_negative';
    case GramPerform = 'gram.perform';
    case DirectExamsPerform = 'direct_exams.perform';
    case IsolatesCreate = 'isolates.create';
    case IdentificationBiochemSetup = 'identification.biochem.setup';
    case IdentificationBiochemRead = 'identification.biochem.read';
    case IdentificationPerform = 'identification.perform';
    case VitekLoad = 'vitek.load';
    case AstSetup = 'ast.setup';
    case AstRead = 'ast.read';
    case AstInterpretationOverride = 'ast.interpretation.override';
    case ReportsValidate = 'reports.validate';
    case ReportsValidateOwnWork = 'reports.validate_own_work';
    case ReportsCorrectIssued = 'reports.correct_issued';
    case CriticalCommunicate = 'critical.communicate';
    case BatchesManageOwn = 'batches.manage_own';
    case BatchesTransfer = 'batches.transfer';
    case BatchesManageAny = 'batches.manage_any';
    case ConfigCatalogsEdit = 'config.catalogs.edit';
    case ConfigClinicalDraft = 'config.clinical.draft';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case StatisticsView = 'statistics.view';

    /**
     * Aprobar y poner vigente la configuración clínica compartida. No es un permiso por
     * sede: lo tiene un único Director técnico designado como responsable (Q-A8,
     * `users.is_clinical_approver`). Es una habilidad del Gate, no un permiso de spatie.
     */
    public const CLINICAL_APPROVE = 'config.clinical.approve';

    /** Ver la configuración clínica: quien la prepara o quien la aprueba. */
    public const CLINICAL_VIEW = 'config.clinical.view';
}
