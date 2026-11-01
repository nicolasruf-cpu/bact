export type SiteSummary = {
    id: number;
    code: string;
    name: string;
};

export type SiteContext = {
    active: SiteSummary | null;
    available: SiteSummary[];
};
