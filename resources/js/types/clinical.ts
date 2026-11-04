export type SampleHeader = {
    sample_study_id: number;
    barcode: string;
    patient: string;
    document_number: string;
    order_number: string | null;
    physician: string | null;
    priority: string;
    priority_label: string;
    sample_type: string;
    collected_at: string | null;
    received_at: string;
    study: string;
    study_status: string;
    protocol: string;
    protocol_version: number;
    is_reference_template: boolean;
};

export type ReportResult = {
    antibiotic: string;
    /** Unidad del método de este antibiótico (informes anteriores a D-0022: no viene). */
    unit?: string;
    value: string | null;
    interpretation: 'S' | 'I' | 'R' | null;
    interpretation_label: string | null;
    /** Reglas del laboratorio aplicadas (comentario para el informe). */
    comment?: string | null;
};

export type ReportIsolate = {
    number: number;
    organism: string | null;
    count: string | null;
    identification_method: string | null;
    no_ast_reason: string | null;
    /** Pruebas marcadas para informar (BLEE, carbapenemasa…). */
    tests?: { name: string; result: string | null }[];
    referrals?: {
        destination: string;
        reason: string;
        result: string | null;
        result_at: string | null;
    }[];
    susceptibility: {
        method: string;
        unit: string;
        /** Antibióticos que faltan leer (D-0022). */
        pending?: number;
        breakpoints: {
            name: string;
            edition: string;
            is_synthetic: boolean;
        } | null;
        results: ReportResult[];
    } | null;
};

export type BloodCultureReport = {
    method: string;
    bottle_type: string;
    instrument: string | null;
    loaded_at: string;
    incubation_days: number;
    status: string;
    positive_at: string | null;
    positivity_source: string | null;
    minutes_to_positivity: number | null;
    gram: {
        result: string;
        description: string | null;
        performed_at: string;
    } | null;
    result: string;
    order_summary: {
        study: string;
        text: string;
        incubating: number;
        pending: number;
    }[];
};

export type DirectExamReport = {
    label: string;
    lines: { field: string; values: string[] }[];
    description: string | null;
    organisms_seen: boolean;
    performed_by: string;
    performed_at: string;
};

export type ReportContent = {
    kind?: string;
    site: string;
    patient: {
        name: string;
        document_number: string;
        birth_date: string | null;
        sex: string;
    };
    order: {
        number: string | null;
        physician: string | null;
        service: string | null;
        priority: string;
    };
    sample: {
        barcode: string;
        type: string;
        collected_at: string | null;
        received_at: string;
    };
    study: string;
    protocol: { name: string; version: number; is_reference_template: boolean };
    result: string | null;
    reading_notes: string | null;
    result_count?: string | null;
    rejection?: {
        reason: string;
        new_sample_requested_to: string | null;
        rejected_at: string;
    } | null;
    other_isolates?: {
        number: number;
        organism: string | null;
        description: string | null;
        count: string | null;
        significance: string;
        significance_label: string;
    }[];
    blood_culture?: BloodCultureReport | null;
    direct_exam?: DirectExamReport | null;
    isolates: ReportIsolate[];
};

export type CriticalAlert = {
    ulid: string;
    summary: string;
    status: string;
    status_label: string;
    opened_at: string;
    closed_at: string | null;
    communications: {
        recipient_name: string;
        recipient_role: string | null;
        channel: string;
        successful: boolean;
        read_back_confirmed: boolean;
        notes: string | null;
        communicated_by: string;
        communicated_at: string;
    }[];
};
