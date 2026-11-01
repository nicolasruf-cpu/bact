/**
 * Tiempo transcurrido legible: "12 min", "3 h 05 min", "2 d 4 h".
 */
export function elapsedSince(iso: string, now: Date = new Date()): string {
    const minutes = Math.max(
        0,
        Math.floor((now.getTime() - new Date(iso).getTime()) / 60000),
    );

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours} h ${String(minutes % 60).padStart(2, '0')} min`;
    }

    return `${Math.floor(hours / 24)} d ${hours % 24} h`;
}

/**
 * Duración en minutos, legible (p. ej., tiempo hasta la positividad): "14 h 20 min".
 */
export function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    if (hours === 0) {
        return `${minutes} min`;
    }

    return `${hours} h ${String(minutes % 60).padStart(2, '0')} min`;
}
