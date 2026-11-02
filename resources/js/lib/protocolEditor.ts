import type { CodeLabel, ProtocolContent } from '@/types/protocol';

/**
 * Código interno a partir del texto visible: sin acentos, en mayúsculas y con guiones.
 * El bacteriólogo escribe el texto; el código lo arma el sistema si queda vacío.
 */
export function codeFromLabel(label: string): string {
    const code = label
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 16)
        .replace(/-+$/g, '');

    return code === '' ? 'OPC' : code;
}

/** Completa los códigos vacíos de una lista sin repetir los que ya existen. */
export function fillCodes<T extends CodeLabel>(items: T[]): T[] {
    const used = new Set(items.map((i) => i.code.trim()).filter(Boolean));

    return items.map((item) => {
        if (item.code.trim() !== '') {
            return { ...item, code: item.code.trim() };
        }
        const base = codeFromLabel(item.label);
        let code = base;
        for (let n = 2; used.has(code); n++) {
            code = `${base}-${n}`;
        }
        used.add(code);

        return { ...item, code };
    });
}

/** Copia del contenido con todos los códigos vacíos completados (al guardar). */
export function withCodes(content: ProtocolContent): ProtocolContent {
    const direct = content.direct_exam_settings;
    const blood = content.blood_culture_settings;

    return {
        ...content,
        reading_options: fillCodes(content.reading_options),
        count_categories: fillCodes(content.count_categories),
        direct_exam_settings: direct
            ? {
                  ...direct,
                  fields: fillCodes(direct.fields).map((field) => ({
                      ...field,
                      options: fillCodes(field.options),
                  })),
              }
            : null,
        blood_culture_settings: blood
            ? {
                  ...blood,
                  bottle_types: fillCodes(blood.bottle_types),
                  gram_options: fillCodes(blood.gram_options),
              }
            : null,
    };
}

/** "36 h (1,5 días)" para leer las horas como las piensa la mesada. */
export function hoursLabel(hours: number | null | undefined): string {
    if (hours === null || hours === undefined || Number.isNaN(hours)) {
        return '—';
    }
    if (hours < 24) {
        return `${hours} h`;
    }
    const days = (hours / 24).toLocaleString('es-AR', {
        maximumFractionDigits: 1,
    });

    return `${hours} h (${days} ${hours === 24 ? 'día' : 'días'})`;
}

/** Sección del editor a la que pertenece un error de validación del servidor. */
export function sectionOfError(key: string): string {
    if (key.startsWith('reading_schedule')) {
        return 'schedule';
    }
    if (
        key.startsWith('reading_options') ||
        key.startsWith('reincubation') ||
        key.startsWith('max_reincubations')
    ) {
        return 'results';
    }
    if (key.startsWith('count_categories')) {
        return 'counts';
    }
    if (key.startsWith('ast_')) {
        return 'ast';
    }
    if (key.startsWith('media')) {
        return 'media';
    }
    if (key.startsWith('direct_exam_settings')) {
        return 'direct';
    }
    if (key.startsWith('blood_culture_settings')) {
        return 'blood';
    }

    return 'notes';
}
