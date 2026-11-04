<script lang="ts">
/** Paneles que le tocan a cada grupo en la muestra del protocolo (D-0021). */
export type AstPanelCoverage = {
    group: string;
    label: string;
    panels: { name: string; is_vitek: boolean; all_samples: boolean }[];
};
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { hoursLabel } from '@/lib/protocolEditor';
import { reviewProtocol } from '@/lib/protocolReview';
import type { ReviewStep } from '@/lib/protocolReview';
import type {
    MediumOption,
    OutcomeOption,
    ProtocolContent,
    ValueLabel,
} from '@/types/protocol';

/**
 * El protocolo contado como lo recorre la mesada: en qué mesada aparece cada paso, qué se
 * registra, qué pasa después y qué dice el informe. Con «Revisá» marca lo que conviene
 * mirar para notar si falta algo. Se actualiza mientras se edita.
 */
const props = defineProps<{
    content: ProtocolContent;
    isBlood: boolean;
    media: MediumOption[];
    atmospheres: ValueLabel[];
    outcomes: OutcomeOption[];
    astPanels: AstPanelCoverage[];
    sampleType: string;
}>();

const withPanel = computed(() =>
    props.astPanels.filter((g) => g.panels.length > 0),
);
const withoutPanel = computed(() =>
    props.astPanels.filter((g) => g.panels.length === 0),
);

const mediumById = computed(
    () => new Map(props.media.map((m) => [m.id, m.name])),
);
const mediumByCode = computed(
    () => new Map(props.media.map((m) => [m.code, m.name])),
);
const atmosphere = (value: string) =>
    props.atmospheres.find((a) => a.value === value)?.label ?? value;
const outcome = (value: string) =>
    props.outcomes.find((o) => o.value === value);

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
const organismOptions = computed(() =>
    (direct.value?.fields ?? []).flatMap((f) =>
        f.options.filter((o) => o.organisms).map((o) => o.label),
    ),
);
const review = computed(() => reviewProtocol(props.content, props.isBlood));
const notesFor = (step: ReviewStep) =>
    review.value.filter((n) => n.step === step).map((n) => n.text);
const empty = '(sin completar)';
</script>

<template>
    <div class="grid gap-4 text-sm" data-test="protocol-summary">
        <p
            v-if="review.length"
            class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Hay {{ review.length }}
            {{ review.length === 1 ? 'punto' : 'puntos' }} para revisar,
            marcados con «Revisá». No impiden aprobar: son preguntas para
            confirmar que no falte nada.
        </p>

        <ol class="relative grid gap-5 border-l pl-5">
            <!-- Recepción -->
            <li class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">Recepción</p>
                <p>
                    Al recibir la muestra se crea
                    <template v-if="isBlood"
                        >la carga del frasco en la
                        <em>Mesada de hemocultivos</em>.</template
                    >
                    <template v-else
                        >la siembra en la <em>Mesada de siembra</em
                        ><template v-if="direct">
                            y el examen directo en la
                            <em>Mesada de procesamiento</em></template
                        >.</template
                    >
                </p>
            </li>

            <!-- Frasco -->
            <li v-if="isBlood && blood" class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    Frasco de hemocultivo
                    <span class="font-normal text-muted-foreground"
                        >· Mesada de hemocultivos</span
                    >
                </p>
                <p>
                    Se carga eligiendo el frasco ({{
                        blood.bottle_types
                            .map((t) => t.label)
                            .filter(Boolean)
                            .join(', ') || empty
                    }})<template v-if="blood.instruments.length">
                        y el equipo ({{
                            blood.instruments.join(', ')
                        }})</template
                    >.
                </p>
                <p>
                    Incuba {{ blood.incubation_days_automated }} días en el
                    equipo o {{ blood.incubation_days_manual }} días en estufa.
                    Sin equipo, se repica a ciegas cada
                    {{ hoursLabel(blood.blind_subculture_interval_hours) }} en
                    {{
                        mediumByCode.get(blood.blind_subculture_medium_code) ??
                        empty
                    }}; si un repique tiene desarrollo, el frasco es positivo y
                    esa placa pasa a lectura.
                </p>
                <p>
                    Positivo: se hace el Gram del frasco, con prioridad
                    inmediata ({{
                        blood.gram_options
                            .map((g) => g.label)
                            .filter(Boolean)
                            .join(' · ') || empty
                    }})<template v-if="blood.communicate_on_positive_gram"
                        >, que <strong>abre un resultado crítico</strong> para
                        comunicar</template
                    ><template v-else
                        >.
                        <strong>No abre un resultado crítico</strong></template
                    >. Después se subcultiva en las placas del paso siguiente.
                </p>
                <p>
                    Negativo: al cumplirse los días se cierra y el informe dice
                    «{{
                        (blood.negative_result_label || empty).replace(
                            '{days}',
                            String(blood.incubation_days_automated),
                        )
                    }}».
                </p>
                <ul
                    v-if="notesFor('blood').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('blood')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>
            </li>

            <!-- Examen directo -->
            <li v-if="direct" class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    {{ direct.label || 'Examen directo' }}
                    <span
                        v-if="direct.urgent"
                        class="text-red-700 dark:text-red-300"
                        >(inmediato)</span
                    >
                    <span class="font-normal text-muted-foreground"
                        >· Mesada de procesamiento</span
                    >
                </p>
                <p>Se registra, en un solo paso:</p>
                <ul class="list-disc pl-5">
                    <li v-for="(f, i) in direct.fields" :key="i">
                        {{ f.label || empty }}:
                        <span class="text-muted-foreground">{{
                            f.options
                                .map((o) => o.label || empty)
                                .join(' · ') || 'sin opciones'
                        }}</span
                        ><span v-if="f.multiple" class="text-muted-foreground">
                            (una o más)</span
                        >
                    </li>
                </ul>
                <p
                    v-if="
                        direct.critical_if_organisms && organismOptions.length
                    "
                >
                    Si se marca {{ organismOptions.join(', ') }},
                    <strong>abre un resultado crítico</strong> para comunicar.
                </p>
                <p v-else-if="!direct.critical_if_organisms">
                    No abre un resultado crítico, aunque se vean
                    microorganismos.
                </p>
                <p class="text-muted-foreground">
                    Fresco y Gram, si los hay, van juntos en este formulario.
                    Mientras no se registre, queda pendiente en la mesada.
                </p>
                <ul
                    v-if="notesFor('direct').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('direct')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>
            </li>
            <li v-else-if="notesFor('direct').length" class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-muted-foreground"
                />
                <p class="font-medium">Sin examen directo</p>
                <p
                    v-for="(n, i) in notesFor('direct')"
                    :key="i"
                    class="text-amber-800 dark:text-amber-300"
                >
                    <strong>Revisá:</strong> {{ n }}
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
                    <span class="font-normal text-muted-foreground"
                        >·
                        {{
                            isBlood
                                ? 'Mesada de hemocultivos'
                                : 'Mesada de siembra'
                        }}</span
                    >
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
                    Caldo
                    {{ b.medium_id ? mediumById.get(b.medium_id) : empty }}: se
                    repica en la <em>Mesada de procesamiento</em> entre las
                    {{ hoursLabel(b.enrichment_subculture_min_hours) }} y las
                    {{ hoursLabel(b.enrichment_subculture_due_hours) }} a
                    {{
                        b.enrichment_subculture_media
                            .map(
                                (t) => mediumByCode.get(t.medium_code) ?? empty,
                            )
                            .join(', ') || empty
                    }}. La última lectura no se registra sin el repique.
                </p>
                <ul
                    v-if="notesFor('media').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('media')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>
            </li>

            <!-- Lecturas -->
            <li class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    Lecturas de las placas
                    <span class="font-normal text-muted-foreground"
                        >· Mesada de lectura</span
                    >
                </p>
                <ul
                    v-if="content.reading_schedule.length"
                    class="list-disc pl-5"
                >
                    <li v-for="(s, i) in content.reading_schedule" :key="i">
                        Lectura {{ i + 1 }}: corresponde a las
                        {{ hoursLabel(s.min_hours) }} y vence a las
                        {{ hoursLabel(s.due_hours) }}
                        <span
                            v-if="i === content.reading_schedule.length - 1"
                            class="text-muted-foreground"
                        >
                            — última</span
                        >
                    </li>
                </ul>
                <p v-else class="text-amber-700 dark:text-amber-300">
                    Todavía no hay lecturas.
                </p>
                <p
                    v-if="content.reading_schedule.length > 1"
                    class="text-muted-foreground"
                >
                    «Sin desarrollo» antes de la última espera la lectura
                    siguiente. Si hay desarrollo, las lecturas programadas se
                    siguen haciendo para registrar desarrollo nuevo o sin
                    desarrollo nuevo.
                </p>
                <ul
                    v-if="notesFor('schedule').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('schedule')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>

                <p v-if="content.reading_options.length" class="mt-2">
                    En cada lectura se elige una de estas opciones. Su texto es
                    el resultado que sale en el informe:
                </p>
                <table
                    v-if="content.reading_options.length"
                    class="w-full text-left"
                >
                    <thead class="text-xs text-muted-foreground">
                        <tr>
                            <th class="py-1 pr-3 font-medium">
                                El informe dice
                            </th>
                            <th class="py-1 font-medium">
                                Qué hace el sistema
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(o, i) in content.reading_options"
                            :key="i"
                            class="border-t align-top"
                        >
                            <td class="py-1 pr-3">«{{ o.label || empty }}»</td>
                            <td class="py-1 text-muted-foreground">
                                {{ outcome(o.outcome)?.description ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="hasReincubate">
                    Reincubar corre la lectura
                    {{ hoursLabel(content.reincubation_hours) }}, hasta
                    {{ content.max_reincubations }}
                    {{ content.max_reincubations === 1 ? 'vez' : 'veces' }}.
                </p>
                <ul
                    v-if="notesFor('results').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('results')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>
            </li>

            <!-- Aislamientos e identificación -->
            <li class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    Aislamientos e identificación
                    <span class="font-normal text-muted-foreground"
                        >· Mesada de identificación</span
                    >
                </p>
                <p>
                    Con desarrollo se carga cada aislamiento: morfotipo, placa
                    <template v-if="content.count_categories.length"
                        >, recuento ({{
                            content.count_categories
                                .map((c) => c.label || empty)
                                .join(' · ')
                        }})</template
                    >
                    y si es significativo. Solo los significativos pasan a
                    identificación y salen en el informe.
                </p>
                <ul
                    v-if="notesFor('counts').length"
                    class="grid gap-1 text-amber-800 dark:text-amber-300"
                >
                    <li v-for="(n, i) in notesFor('counts')" :key="i">
                        <strong>Revisá:</strong> {{ n }}
                    </li>
                </ul>
            </li>

            <!-- Antibiograma -->
            <li class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    Antibiograma
                    <span class="font-normal text-muted-foreground"
                        >· Mesada de antibiogramas</span
                    >
                </p>
                <p>
                    Se monta para cada aislamiento identificado; si no
                    corresponde, se indica el motivo al identificar.
                    <template v-if="content.ast_read_min_hours !== null">
                        Se lee entre las
                        {{ hoursLabel(content.ast_read_min_hours) }} y las
                        {{ hoursLabel(content.ast_read_due_hours) }} desde que
                        se monta.</template
                    >
                </p>
                <p
                    v-if="content.ast_read_min_hours === null"
                    class="text-amber-700 dark:text-amber-300"
                >
                    Faltan las horas de lectura.
                </p>
                <p>
                    El panel depende del grupo del germen y de la muestra ({{
                        sampleType
                    }}). Hoy, en esta muestra:
                </p>
                <table v-if="withPanel.length" class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="py-1 pr-3 font-normal">Grupo</th>
                            <th class="py-1 font-normal">
                                Panel que se propone (y otros que se pueden
                                elegir)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="g in withPanel"
                            :key="g.group"
                            class="border-t align-top"
                        >
                            <td class="py-1 pr-3">{{ g.label }}</td>
                            <td class="py-1">
                                <span
                                    v-for="(panel, i) in g.panels"
                                    :key="panel.name"
                                    :class="{
                                        'text-muted-foreground': i > 0,
                                    }"
                                    >{{ i > 0 ? ' · ' : '' }}{{ panel.name
                                    }}{{ panel.is_vitek ? ' (VITEK)' : ''
                                    }}{{
                                        panel.all_samples
                                            ? ', para todas las muestras'
                                            : ''
                                    }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="withoutPanel.length"
                    class="text-amber-700 dark:text-amber-300"
                >
                    <strong>Revisá:</strong> sin panel para
                    {{ withoutPanel.map((g) => g.label).join(', ') }}. Esos
                    gérmenes solo se pueden identificar sin antibiograma,
                    indicando el motivo.
                </p>
                <p class="text-muted-foreground">
                    Los paneles se definen en Administración → Paneles de
                    antibiograma, y la interpretación sale de los puntos de
                    corte vigentes; ninguno de los dos se define en el
                    protocolo.
                </p>
            </li>

            <!-- Validación e informe -->
            <li class="grid gap-1">
                <span
                    class="absolute -left-1.5 mt-1 size-3 rounded-full border-2 border-background bg-primary"
                />
                <p class="font-medium">
                    Validación e informe
                    <span class="font-normal text-muted-foreground"
                        >· Bandeja de validación</span
                    >
                </p>
                <p>
                    Cuando el cultivo se cierra y los aislamientos están
                    resueltos, el estudio pasa a validación. El informe lleva:
                </p>
                <ul class="list-disc pl-5">
                    <li v-if="direct">
                        lo registrado en
                        {{ direct.label || 'el examen directo' }};
                    </li>
                    <li v-if="isBlood">
                        el frasco, el Gram y el resumen de los hemocultivos de
                        la orden;
                    </li>
                    <li>
                        el resultado: el texto de la opción de lectura elegida;
                    </li>
                    <li>
                        cada aislamiento significativo, con su
                        identificación<template
                            v-if="content.count_categories.length"
                            >, recuento</template
                        >
                        y antibiograma.
                    </li>
                </ul>
                <p class="text-muted-foreground">
                    Mientras el estudio está en curso se puede emitir un informe
                    preliminar con lo registrado hasta ese momento<template
                        v-if="direct || isBlood"
                    >
                        (por ejemplo,
                        {{ isBlood ? 'el Gram' : 'el directo' }})</template
                    >.
                </p>
            </li>
        </ol>

        <div class="grid gap-1 rounded-md border p-3 text-muted-foreground">
            <p class="font-medium text-foreground">
                Lo que este protocolo no define
            </p>
            <ul class="list-disc pl-5">
                <li>Puntos de corte: se cargan aparte, en Administración.</li>
                <li>
                    Paneles de antibiograma: se definen aparte, en
                    Administración, por grupo de germen y muestra.
                </li>
                <li>Criterios de rechazo de muestras: todavía no están.</li>
                <li v-if="content.allow_bulk_negative_confirmation">
                    La confirmación de negativos en bloque está tildada, pero
                    ninguna mesada la usa todavía.
                </li>
            </ul>
        </div>
    </div>
</template>
