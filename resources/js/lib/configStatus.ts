/** Colores de los estados de una versión de configuración clínica. */
export function statusClass(status: string): string {
    switch (status) {
        case 'active':
            return 'border-emerald-600/40 bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200';
        case 'approved':
            return 'border-sky-600/40 bg-sky-50 text-sky-900 dark:bg-sky-950/40 dark:text-sky-200';
        case 'draft':
            return 'border-amber-600/40 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200';
        default:
            return 'text-muted-foreground';
    }
}
