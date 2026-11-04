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

/** Valor para <input type="datetime-local"> en la hora local del navegador ("ahora" por defecto). */
export function localDateTimeInput(date: Date = new Date()): string {
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

/** Valor para <input type="date"> en la fecha local del navegador ("hoy" por defecto). */
export function localDateInput(date: Date = new Date()): string {
    return localDateTimeInput(date).slice(0, 10);
}
