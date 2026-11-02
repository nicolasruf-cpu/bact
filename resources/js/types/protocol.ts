/** Contenido editable de una versión de protocolo (ProtocolAdminQuery::detail). */
export type CodeLabel = { code: string; label: string };
export type ReadingOption = CodeLabel & { outcome: string };
export type SubcultureTarget = {
    medium_code: string;
    incubation_temperature: string;
    atmosphere: string;
};
export type ProtocolMedium = {
    medium_id: number | null;
    inoculum: string;
    incubation_temperature: string;
    atmosphere: string;
    is_enrichment: boolean;
    enrichment_subculture_min_hours: number | null;
    enrichment_subculture_due_hours: number | null;
    enrichment_subculture_media: SubcultureTarget[];
};
export type DirectField = CodeLabel & {
    multiple: boolean;
    options: (CodeLabel & { organisms: boolean })[];
};
export type DirectExam = {
    label: string;
    urgent: boolean;
    critical_if_organisms: boolean;
    fields: DirectField[];
};
export type BloodSettings = {
    incubation_days_automated: number;
    incubation_days_manual: number;
    blind_subculture_interval_hours: number;
    blind_subculture_medium_code: string;
    instruments: string[];
    bottle_types: CodeLabel[];
    gram_options: CodeLabel[];
    negative_result_label: string;
    communicate_on_positive_gram: boolean;
};
export type ReadingStep = { min_hours: number; due_hours: number };
export type ProtocolContent = {
    notes: string | null;
    source: string | null;
    reading_schedule: ReadingStep[];
    reincubation_hours: number | null;
    max_reincubations: number;
    allow_bulk_negative_confirmation: boolean;
    reading_options: ReadingOption[];
    count_categories: CodeLabel[];
    ast_read_min_hours: number | null;
    ast_read_due_hours: number | null;
    media: ProtocolMedium[];
    direct_exam_settings: DirectExam | null;
    blood_culture_settings: BloodSettings | null;
};

export type MediumOption = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};
export type ValueLabel = { value: string; label: string };
export type OutcomeOption = ValueLabel & { description: string };
