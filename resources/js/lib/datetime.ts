// Los tiempos se guardan en UTC y se muestran en hora de Argentina (docs/DATABASE.md).
const TIME_ZONE = 'America/Argentina/Buenos_Aires';

const dateTimeFormat = new Intl.DateTimeFormat('es-AR', {
    timeZone: TIME_ZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
});

const timeFormat = new Intl.DateTimeFormat('es-AR', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

export function formatDateTime(iso: string | null | undefined): string {
    return iso ? dateTimeFormat.format(new Date(iso)) : '—';
}

export function formatTime(iso: string | null | undefined): string {
    return iso ? timeFormat.format(new Date(iso)) : '—';
}
