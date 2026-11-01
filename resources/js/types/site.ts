export type SiteSummary = {
    id: number;
    code: string;
    name: string;
};

export type SiteContext = {
    active: SiteSummary | null;
    available: SiteSummary[];
};

export type OpenBatch = {
    ulid: string;
    code: string;
    type_label: string;
    status: string;
    status_label: string;
    items: number;
    done: number;
};

export type OpenCriticalAlert = {
    ulid: string;
    summary: string;
    opened_at: string;
    sample_study_id: number;
    attempts: number;
    url: string;
};
