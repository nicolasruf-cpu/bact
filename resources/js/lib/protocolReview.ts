import { hoursLabel } from '@/lib/protocolEditor';
import type { ProtocolContent } from '@/types/protocol';

/**
 * Puntos para revisar al leer un protocolo, por paso del resumen. No son reglas clínicas
 * ni bloquean la aprobación (eso es ProtocolVersionRules::missingItems): señalan lo que
 * el sistema va a hacer con lo cargado, para que quien lo lee note si falta algo.
 */
export type ReviewStep =
    | 'blood'
    | 'direct'
    | 'media'
    | 'schedule'
    | 'results'
    | 'counts';

export type ReviewNote = { step: ReviewStep; text: string };

export function reviewProtocol(
    content: ProtocolContent,
    isBlood: boolean,
): ReviewNote[] {
    const notes: ReviewNote[] = [];
    const add = (step: ReviewStep, text: string) => notes.push({ step, text });

    // Frasco
    const blood = content.blood_culture_settings;
    if (isBlood && blood) {
        if (blood.bottle_types.length === 0) {
            add('blood', 'No hay tipos de frasco para elegir al cargarlo.');
        }
        if (blood.gram_options.length === 0) {
            add(
                'blood',
                'El Gram del frasco positivo no tiene opciones: no se va a poder registrar.',
            );
        }
        if (blood.instruments.length === 0) {
            add(
                'blood',
                'No hay equipos cargados: no se va a poder indicar en qué equipo se incuba el frasco.',
            );
        }
    }

    // Examen directo
    const direct = content.direct_exam_settings;
    if (!direct && !isBlood) {
        add(
            'direct',
            'Este estudio no lleva examen directo. Si en la mesada se hace un fresco o un Gram de la muestra, hay que agregarlo en «Examen directo».',
        );
    }
    if (direct) {
        if (direct.fields.length === 0) {
            add(
                'direct',
                'El directo no tiene campos: no se va a poder registrar nada.',
            );
        }
        direct.fields
            .filter((f) => f.options.length === 0)
            .forEach((f) =>
                add(
                    'direct',
                    `El campo «${f.label || 'sin nombre'}» no tiene opciones para elegir.`,
                ),
            );
        const anyOrganisms = direct.fields.some((f) =>
            f.options.some((o) => o.organisms),
        );
        if (direct.critical_if_organisms && !anyOrganisms) {
            add(
                'direct',
                'Está indicado que abre un resultado crítico, pero ninguna opción está marcada como «Indica microorganismos»: el crítico nunca se va a abrir.',
            );
        }
    }

    // Siembra
    const plates = content.media.filter((m) => !m.is_enrichment);
    if (plates.some((m) => !m.inoculum.trim())) {
        add('media', 'Hay medios sin el inóculo indicado.');
    }
    content.media
        .filter(
            (m) =>
                m.is_enrichment && m.enrichment_subculture_media.length === 0,
        )
        .forEach(() =>
            add('media', 'Hay un caldo sin los medios a los que se repica.'),
        );

    // Lecturas: las placas del repique de un caldo necesitan la incubación de la primera
    // lectura antes de la lectura siguiente (ReadingSchedule::nextReadingAvailableAt).
    const schedule = content.reading_schedule;
    const first = schedule[0];
    const last = schedule[schedule.length - 1];
    if (first && last) {
        content.media
            .filter(
                (m) =>
                    m.is_enrichment &&
                    m.enrichment_subculture_min_hours !== null,
            )
            .forEach((m) => {
                const ready =
                    (m.enrichment_subculture_min_hours ?? 0) + first.min_hours;
                if (ready > last.min_hours) {
                    add(
                        'schedule',
                        `Las placas del repique del caldo (a las ${hoursLabel(m.enrichment_subculture_min_hours)}) cumplen su primera incubación a las ${hoursLabel(ready)}, después de la última lectura programada (${hoursLabel(last.min_hours)}). La mesada va a avisar que todavía no se cumplió el tiempo.`,
                    );
                }
            });
    }

    // Resultados posibles
    const outcomes = content.reading_options.map((o) => o.outcome);
    if (!outcomes.includes('mixed_flora')) {
        add(
            'results',
            '¿Hace falta una opción de flora mixta (contaminado)? Hoy no hay ninguna.',
        );
    }
    if (!outcomes.includes('not_significant')) {
        add(
            'results',
            '¿Hace falta una opción de desarrollo no significativo? Hoy no hay ninguna.',
        );
    }
    if (content.reading_options.some((o) => !o.label.trim())) {
        add(
            'results',
            'Hay opciones de lectura sin texto: ese texto es el que sale en el informe.',
        );
    }

    // Recuentos
    if (!isBlood && content.count_categories.length === 0) {
        add(
            'counts',
            'Los aislamientos se cargan sin recuento. ¿Este estudio informa recuento?',
        );
    }

    return notes;
}
