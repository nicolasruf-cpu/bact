<script setup lang="ts">
import { computed } from 'vue';
import { hoursLabel } from '@/lib/protocolEditor';
import type {
    MediumOption,
    OutcomeOption,
    ProtocolContent,
    ValueLabel,
} from '@/types/protocol';

/**
 * El protocolo contado como lo recorre la mesada: qué se hace al recibir la muestra,
 * qué se siembra, cuándo se lee y qué se puede informar. Se actualiza mientras se edita.
 */
const props = defineProps<{
    content: ProtocolContent;
    isBlood: boolean;
    media: MediumOption[];
    atmospheres: ValueLabel[];
    outcomes: OutcomeOption[];
}>();

const mediumById = computed(
    () => new Map(props.media.map((m) => [m.id, m.name])),
);
const mediumByCode = computed(
    () => new Map(props.media.map((m) => [m.code, m.name])),
);
const atmosphere = (value: string) =>
    props.atmospheres.find((a) => a.value === value)?.label ?? value;
const outcomeLabel = (value: string) =>
    props.outcomes.find((o) => o.value === value)?.label ?? value;

const plates = computed(() =>
    props.content.media.filter((m) => !m.is_enrichment),
);
const broths = computed(() =>
    props.content.media.filter((m) => m.is_enrichment),
);
const hasReincubate = computed(() =>
    props.content.reading_options.some((o) => o.outcome === 'reincubate'),
);
const blood = computed(() => props.content.blood_culture_settings);
const direct = computed(() => props.content.direct_exam_settings);
const empty = '(sin completar)';
</script>

<template>
    <ol
        class="relative grid gap-4 border-l pl-5 text-sm"
        data-test="protocol-summary"
    >
        <!-- Frasco -->
        <li v-if="isBlood && blood" class="grid gap-1">
            <span
                class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
            />
            <p class="font-medium">Frasco de hemocultivo</p>
            <p>
                Frascos:
                {{
                    blood.bottle_types
                        .map((t) => t.label)
                        .filter(Boolean)
                        .join(', ') || empty
                }}.
                <template v-if="blood.instruments.length">
                    Equipos: {{ blood.instruments.join(', ') }}.
                </template>
            </p>
            <p>
                Incuba {{ blood.incubation_days_automated }} días en el equipo o
                {{ blood.incubation_days_manual }} días en estufa. Sin equipo,
                repique a ciegas cada
                {{ hoursLabel(blood.blind_subculture_interval_hours) }} en
                {{
                    mediumByCode.get(blood.blind_subculture_medium_code) ??
                    empty
                }}.
            </p>
            <p>
                Positivo: se hace el Gram del frasco ({{
                    blood.gram_options
                        .map((g) => g.label)
                        .filter(Boolean)
                        .join(' · ') || empty
                }})<template v-if="blood.communicate_on_positive_gram"
                    >, que <strong>abre un resultado crítico</strong></template
                >, y se subcultiva en las placas de abajo.
            </p>
            <p class="text-muted-foreground">
                Negativo: se informa «{{
                    (blood.negative_result_label || empty).replace(
                        '{days}',
                        String(blood.incubation_days_automated),
                    )
                }}».
            </p>
        </li>

        <!-- Directo -->
        <li v-if="direct" class="grid gap-1">
            <span
                class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
            />
            <p class="font-medium">
                Al recibir la muestra: {{ direct.label || 'examen directo' }}
                <span
                    v-if="direct.urgent"
                    class="text-red-700 dark:text-red-300"
                    >(inmediato)</span
                >
            </p>
            <p>
                Se registra:
                {{
                    direct.fields
                        .map(
                            (f) =>
                                `${f.label || empty}${f.multiple ? ' (una o más opciones)' : ''}`,
                        )
                        .join('; ')
                }}.
            </p>
            <p v-if="direct.critical_if_organisms">
                Si se marca una opción que indica microorganismos,
                <strong>abre un resultado crítico</strong>.
            </p>
        </li>

        <!-- Siembra -->
        <li class="grid gap-1">
            <span
                class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
            />
            <p class="font-medium">
                {{
                    isBlood
                        ? 'Subcultivo del frasco positivo'
                        : 'Siembra (hora 0)'
                }}
            </p>
            <ul v-if="plates.length" class="list-disc pl-5">
                <li v-for="(m, i) in plates" :key="i">
                    {{ m.medium_id ? mediumById.get(m.medium_id) : empty }}
                    <span class="text-muted-foreground">
                        · {{ m.inoculum || 'inóculo sin completar' }} ·
                        {{ m.incubation_temperature }} ·
                        {{ atmosphere(m.atmosphere) }}</span
                    >
                </li>
            </ul>
            <p v-else class="text-amber-700 dark:text-amber-300">
                Todavía no hay placas.
            </p>
            <p v-for="(b, i) in broths" :key="`b${i}`">
                Caldo {{ b.medium_id ? mediumById.get(b.medium_id) : empty }}:
                se repica entre las
                {{ b.enrichment_subculture_min_hours ?? '?' }} y las
                {{ b.enrichment_subculture_due_hours ?? '?' }} h a
                {{
                    b.enrichment_subculture_media
                        .map((t) => mediumByCode.get(t.medium_code) ?? empty)
                        .join(', ') || empty
                }}.
            </p>
        </li>

        <!-- Lecturas -->
        <li class="grid gap-1">
            <span
                class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
            />
            <p class="font-medium">Lecturas de las placas</p>
            <ul v-if="content.reading_schedule.length" class="list-disc pl-5">
                <li v-for="(s, i) in content.reading_schedule" :key="i">
                    Lectura {{ i + 1 }}: aparece en la mesada a las
                    {{ hoursLabel(s.min_hours) }} y vence a las
                    {{ hoursLabel(s.due_hours) }}
                    <span
                        v-if="i === content.reading_schedule.length - 1"
                        class="text-muted-foreground"
                    >
                        — última: con «sin desarrollo» el cultivo se cierra
                        negativo</span
                    >
                </li>
            </ul>
            <p v-else class="text-amber-700 dark:text-amber-300">
                Todavía no hay lecturas.
            </p>
            <p v-if="content.reading_options.length">
                En cada lectura se elige:
                <span
                    v-for="(o, i) in content.reading_options"
                    :key="i"
                    class="mr-1 inline-block rounded border px-1.5 text-xs"
                    :title="outcomeLabel(o.outcome)"
                    >{{ o.label || empty }}</span
                >
            </p>
            <p v-if="hasReincubate">
                Reincubar corre la lectura
                {{ hoursLabel(content.reincubation_hours) }}, hasta
                {{ content.max_reincubations }}
                {{ content.max_reincubations === 1 ? 'vez' : 'veces' }}.
            </p>
            <p v-if="content.count_categories.length">
                Recuento de cada aislamiento:
                {{
                    content.count_categories
                        .map((c) => c.label || empty)
                        .join(' · ')
                }}.
            </p>
        </li>

        <!-- Antibiograma -->
        <li class="grid gap-1">
            <span
                class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
            />
            <p class="font-medium">Antibiograma</p>
            <p v-if="content.ast_read_min_hours !== null">
                Se lee entre las {{ hoursLabel(content.ast_read_min_hours) }} y
                las {{ hoursLabel(content.ast_read_due_hours) }} desde que se
                monta.
            </p>
            <p v-else class="text-amber-700 dark:text-amber-300">
                Faltan las horas de lectura.
            </p>
        </li>
    </ol>
</template>
