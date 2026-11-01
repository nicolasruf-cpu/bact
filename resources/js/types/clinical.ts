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
    value: string | null;
    interpretation: 'S' | 'I' | 'R' | null;
    interpretation_label: string | null;
};

export type ReportIsolate = {
    number: number;
    organism: string | null;
    count: string | null;
    identification_method: string | null;
    no_ast_reason: string | null;
    susceptibility: {
        method: string;
        unit: string;
        breakpoints: {
            name: string;
            edition: string;
            is_synthetic: boolean;
        } | null;
        results: ReportResult[];
    } | null;
};

export type ReportContent = {
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
    isolates: ReportIsolate[];
};
