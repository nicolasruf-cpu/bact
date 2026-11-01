<?php

namespace App\Modules\Workflow\Enums;

/**
 * Tipos de actividad (docs/functional/ACTIVITY_CATALOG.md). Se agregan a medida que se implementan.
 */
enum ActivityType: string
{
    case Inoculate = 'inoculate';
    case ReadCulture = 'read_culture';
    case Identify = 'identify';
    case AstSetup = 'ast_setup';
    case AstRead = 'ast_read';
    case ValidateReport = 'validate_report';
    case LoadBottle = 'load_bottle';
    case BlindSubculture = 'blind_subculture';
    case ReadBlindSubculture = 'read_blind_subculture';
    case ConfirmNegativeBottle = 'confirm_negative_bottle';
    case GramStain = 'gram_stain';
    case SubcultureBottle = 'subculture_bottle';
    case CommunicateCritical = 'communicate_critical';
    case DirectExam = 'direct_exam';
    case SubcultureEnrichment = 'subculture_enrichment';

    public function label(): string
    {
        return match ($this) {
            self::Inoculate => 'Siembra',
            self::ReadCulture => 'Lectura de cultivo',
            self::Identify => 'Identificación',
            self::AstSetup => 'Preparación de antibiograma',
            self::AstRead => 'Lectura de antibiograma',
            self::ValidateReport => 'Validación',
            self::LoadBottle => 'Carga de frasco',
            self::BlindSubculture => 'Repique',
            self::ReadBlindSubculture => 'Lectura de repique',
            self::ConfirmNegativeBottle => 'Descarga de frasco negativo',
            self::GramStain => 'Gram de frasco positivo',
            self::SubcultureBottle => 'Subcultivo de frasco positivo',
            self::CommunicateCritical => 'Comunicación de resultado crítico',
            self::DirectExam => 'Examen directo',
            self::SubcultureEnrichment => 'Repique de caldo de enriquecimiento',
        };
    }
}
