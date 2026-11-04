<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    CircleDot,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProtocolSection from '@/components/domain/ProtocolSection.vue';
import ProtocolSummary from '@/components/domain/ProtocolSummary.vue';
import type { AstPanelCoverage } from '@/components/domain/ProtocolSummary.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { statusClass } from '@/lib/configStatus';
import { formatDateTime } from '@/lib/datetime';
import { hoursLabel, sectionOfError, withCodes } from '@/lib/protocolEditor';
import {
    activate,
    approve,
    draft,
    retire,
    show,
    update,
} from '@/routes/admin/protocol-versions';
import { index as protocolsIndex } from '@/routes/admin/protocols';
import type {
    MediumOption,
    OutcomeOption,
    ProtocolContent,
    ProtocolMedium,
    ValueLabel,
} from '@/types/protocol';

const props = defineProps<{
    version: {
        id: number;
        protocol: {
            id: number;
            name: string;
            study: string;
            sample_type: string;
        };
        version: number;
        status: string;
        status_label: string;
        processing_kind: string;
        is_reference_template: boolean;
        lock_version: number;
        created_by: string | null;
        approved_by: string | null;
        approved_at: string | null;
        activated_at: string | null;
        retired_at: string | null;
        samples_count: number;
        missing: { section: string; message: string }[];
        ast_panels: AstPanelCoverage[];
        content: ProtocolContent;
        siblings: {
            id: number;
            version: number;
            status: string;
            status_label: string;
        }[];
    };
    media: MediumOption[];
    atmospheres: ValueLabel[];
    outcomes: OutcomeOption[];
    can: { draft: boolean; approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Protocolos', href: protocolsIndex() },
            { title: 'Versión', href: '#' },
        ],
    },
});

const isDraft = computed(() => props.version.status === 'draft');
const editable = computed(() => isDraft.value && props.can.draft);
const isBlood = computed(
    () => props.version.processing_kind === 'blood_bottle',
);

const form = useForm({
    content: JSON.parse(
        JSON.stringify(props.version.content),
    ) as ProtocolContent,
    lock_version: props.version.lock_version,
});
const c = form.content;

function save() {
    // Los códigos internos se arman solos a partir del texto si quedaron vacíos.
    form.transform((data) => ({
        ...data,
        content: withCodes(data.content),
    })).put(update(props.version.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            // Trae lo que guardó el servidor (con los códigos armados) sin perder la referencia de `c`.
            Object.assign(c, JSON.parse(JSON.stringify(props.version.content)));
            form.defaults();
        },
    });
}

// Después de guardar o revisar, el servidor devuelve la versión con su nuevo lock_version.
watch(
    () => props.version.lock_version,
    (lock) => {
        form.lock_version = lock;
        form.defaults('lock_version', lock);
        review.lock_version = lock;
    },
);

// ---- Secciones, faltantes y errores ----
type SectionId =
    | 'summary'
    | 'blood'
    | 'media'
    | 'direct'
    | 'schedule'
    | 'results'
    | 'counts'
    | 'ast'
    | 'notes';

const sections = computed<{ id: SectionId; title: string }[]>(() =>
    isBlood.value
        ? [
              { id: 'summary', title: 'Resumen' },
              { id: 'blood', title: 'Frasco' },
              { id: 'media', title: 'Subcultivo del positivo' },
              { id: 'schedule', title: 'Lecturas' },
              { id: 'results', title: 'Qué se informa al leer' },
              { id: 'counts', title: 'Recuentos' },
              { id: 'ast', title: 'Antibiograma' },
              { id: 'direct', title: 'Examen directo' },
              { id: 'notes', title: 'Fuente y notas' },
          ]
        : [
              { id: 'summary', title: 'Resumen' },
              { id: 'media', title: 'Siembra' },
              { id: 'direct', title: 'Examen directo' },
              { id: 'schedule', title: 'Lecturas' },
              { id: 'results', title: 'Qué se informa al leer' },
              { id: 'counts', title: 'Recuentos' },
              { id: 'ast', title: 'Antibiograma' },
              { id: 'notes', title: 'Fuente y notas' },
          ],
);
const order = (id: SectionId) => sections.value.findIndex((s) => s.id === id);
const sectionTitle = (id: SectionId) =>
    sections.value.find((s) => s.id === id)?.title ?? '';

const missingBySection = computed(() => {
    const map: Record<string, string[]> = {};
    for (const m of props.version.missing) {
        (map[m.section] ??= []).push(m.message);
    }

    return map;
});

// "media.1.inoculum" → "Medio 2: …" para saber en qué fila está el problema.
const rowNames: Record<string, string> = {
    reading_schedule: 'Lectura',
    reading_options: 'Opción',
    count_categories: 'Categoría',
    media: 'Medio',
    fields: 'Campo',
    bottle_types: 'Tipo de frasco',
    gram_options: 'Opción del Gram',
};
function describeError(key: string, message: string): string {
    const parts = key.split('.');
    const where: string[] = [];
    parts.forEach((part, i) => {
        const name = rowNames[parts[i - 1] ?? ''];
        if (/^\d+$/.test(part) && name) {
            where.push(`${name} ${Number(part) + 1}`);
        }
    });

    return where.length ? `${where.join(' › ')}: ${message}` : message;
}
const errorsBySection = computed(() => {
    const map: Record<string, string[]> = {};
    for (const [key, message] of Object.entries(
        form.errors as Record<string, string>,
    )) {
        (map[sectionOfError(key)] ??= []).push(describeError(key, message));
    }

    return map;
});
const errorCount = computed(() => Object.keys(form.errors).length);
const hasError = (prefix: string) =>
    Object.keys(form.errors).some(
        (k) => k === prefix || k.startsWith(`${prefix}.`),
    );

// Los códigos internos quedan ocultos; se muestran si se piden o si alguno tiene un error.
const showCodes = ref(false);
watch(
    () => Object.keys(form.errors),
    (keys) => {
        if (keys.some((k) => k.endsWith('.code'))) {
            showCodes.value = true;
        }
    },
);

// ---- Altas en listas ----
const blankMedium = (enrichment = false): ProtocolMedium => ({
    medium_id: null,
    inoculum: '',
    incubation_temperature: '35 ± 2 °C',
    atmosphere: 'aerobic',
    is_enrichment: enrichment,
    enrichment_subculture_min_hours: null,
    enrichment_subculture_due_hours: null,
    enrichment_subculture_media: [],
    read_from_hours: null,
    read_until_hours: null,
    bottle_types: [],
});

function addReading() {
    const last = c.reading_schedule[c.reading_schedule.length - 1];
    // La nueva lectura arranca donde vencía la anterior; las horas las ajusta el laboratorio.
    c.reading_schedule.push(
        last
            ? { min_hours: last.due_hours, due_hours: last.due_hours }
            : { min_hours: 0, due_hours: 0 },
    );
}

function toggleDirectExam(on: boolean) {
    c.direct_exam_settings = on
        ? {
              label: 'Examen directo',
              urgent: false,
              critical_if_organisms: false,
              fields: [{ code: '', label: '', multiple: false, options: [] }],
          }
        : null;
}

if (isBlood.value && c.blood_culture_settings === null) {
    c.blood_culture_settings = {
        incubation_days_automated: 5,
        incubation_days_manual: 7,
        blind_subculture_interval_hours: 24,
        blind_subculture_medium_code: '',
        instruments: [],
        bottle_types: [],
        gram_options: [],
        negative_result_label: '',
        communicate_on_positive_gram: false,
    };
}

const instruments = computed({
    get: () => c.blood_culture_settings?.instruments.join(', ') ?? '',
    set: (value: string) => {
        if (c.blood_culture_settings) {
            c.blood_culture_settings.instruments = value
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
        }
    },
});

const hasReincubate = computed(() =>
    c.reading_options.some((o) => o.outcome === 'reincubate'),
);
const outcome = (value: string) =>
    props.outcomes.find((o) => o.value === value);

// Barra de tiempo de las lecturas: cada ventana desde la hora en que aparece hasta que vence.
const timelineEnd = computed(() =>
    Math.max(24, ...c.reading_schedule.map((s) => Number(s.due_hours) || 0)),
);
const pct = (h: number) =>
    `${Math.min(100, Math.max(0, ((Number(h) || 0) / timelineEnd.value) * 100))}%`;

// ---- Revisión ----
const review = useForm({
    lock_version: props.version.lock_version,
    note: '',
    reason: '',
});
const retiring = ref(false);

function doApprove() {
    review.post(approve(props.version.id).url, { preserveScroll: true });
}
function doActivate() {
    review.post(activate(props.version.id).url, { preserveScroll: true });
}
function doRetire() {
    review.post(retire(props.version.id).url, {
        preserveScroll: true,
        onSuccess: () => (retiring.value = false),
    });
}
function newDraft() {
    router.post(draft(props.version.id).url);
}

const existingDraft = computed(() =>
    props.version.siblings.find(
        (s) => s.status === 'draft' && s.id !== props.version.id,
    ),
);
const canRetire = computed(
    () =>
        props.version.status !== 'retired' &&
        (props.can.approve || (isDraft.value && props.can.draft)),
);
const reviewErrors = computed(() =>
    Object.values(review.errors as Record<string, string>),
);

const lifecycle = [
    { status: 'draft', label: 'Borrador' },
    { status: 'approved', label: 'Aprobada' },
    { status: 'active', label: 'Vigente' },
];
const lifecycleIndex = computed(() =>
    lifecycle.findIndex((s) => s.status === props.version.status),
);
const nextStep = computed(() => {
    switch (props.version.status) {
        case 'draft':
            if (props.version.missing.length) {
                return `Completá lo que falta (${props.version.missing.length}) y guardá. Después la aprueba el Director técnico responsable.`;
            }

            return props.can.approve
                ? 'Está completa: revisala y aprobala.'
                : 'Está completa. Falta que la apruebe el Director técnico responsable.';
        case 'approved':
            return 'Aprobada. Cuando se ponga vigente, las muestras que se reciban desde ese momento usan esta versión.';
        case 'active':
            return 'Es la versión que se usa con las muestras que se reciben. Para cambiar algo, prepará una versión nueva: esta no se modifica.';
        default:
            return 'Retirada: queda solo para consulta.';
    }
});

const inputClass = 'h-9 rounded-md border bg-background px-2 text-sm';
const numberClass = `${inputClass} w-20`;
const hintClass = 'text-xs text-muted-foreground';
</script>

<template>
    <Head :title="`${props.version.protocol.name} v${props.version.version}`" />

    <div class="grid gap-4 p-4">
        <!-- Encabezado -->
        <section class="grid gap-3 rounded-lg border p-4">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">
                    {{ props.version.protocol.name }} · v{{
                        props.version.version
                    }}
                </h1>
                <span
                    class="rounded border px-2 py-0.5 text-xs"
                    :class="statusClass(props.version.status)"
                    data-test="version-status"
                    >{{ props.version.status_label }}</span
                >
                <Badge variant="outline">{{
                    isBlood ? 'Frasco de hemocultivo' : 'Cultivo en placa'
                }}</Badge>
            </div>

            <!-- Ciclo de la versión -->
            <ol class="flex flex-wrap items-center gap-2 text-xs">
                <li
                    v-for="(s, i) in lifecycle"
                    :key="s.status"
                    class="flex items-center gap-2"
                >
                    <span
                        class="rounded-full border px-2 py-0.5"
                        :class="
                            props.version.status === 'retired'
                                ? 'text-muted-foreground'
                                : i === lifecycleIndex
                                  ? 'border-primary bg-primary text-primary-foreground'
                                  : i < lifecycleIndex
                                    ? 'border-primary/40 text-foreground'
                                    : 'text-muted-foreground'
                        "
                        >{{ i + 1 }}. {{ s.label }}</span
                    >
                    <span
                        v-if="i < lifecycle.length - 1"
                        class="text-muted-foreground"
                        >→</span
                    >
                </li>
            </ol>
            <p class="text-sm" data-test="next-step">{{ nextStep }}</p>

            <p
                v-if="props.version.is_reference_template"
                class="flex items-start gap-2 rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                Plantilla de referencia genérica: el Director técnico tiene que
                revisarla y ajustarla antes de usarla con pacientes (D-0008).
            </p>
            <p class="text-sm text-muted-foreground">
                <span v-if="props.version.created_by"
                    >Preparó: {{ props.version.created_by }} · </span
                ><span v-if="props.version.approved_by"
                    >Aprobó: {{ props.version.approved_by }} ({{
                        formatDateTime(props.version.approved_at!)
                    }}) · </span
                ><span v-if="props.version.activated_at"
                    >Vigente desde
                    {{ formatDateTime(props.version.activated_at) }} · </span
                ><span v-if="props.version.retired_at"
                    >Retirada
                    {{ formatDateTime(props.version.retired_at) }} · </span
                >{{ props.version.samples_count }} estudios recibidos con esta
                versión.
            </p>
            <nav class="flex flex-wrap items-center gap-1 text-xs">
                <span class="text-muted-foreground">Versiones:</span>
                <Link
                    v-for="s in props.version.siblings"
                    :key="s.id"
                    :href="show(s.id)"
                    class="rounded border px-2 py-0.5"
                    :class="[
                        statusClass(s.status),
                        { 'ring-2 ring-primary': s.id === props.version.id },
                    ]"
                    >v{{ s.version }} · {{ s.status_label }}</Link
                >
            </nav>

            <div class="flex flex-wrap items-end gap-2">
                <Button
                    v-if="!isDraft && props.can.draft"
                    variant="outline"
                    data-test="new-draft"
                    @click="newDraft"
                    >{{
                        existingDraft
                            ? `Seguir con el borrador v${existingDraft.version}`
                            : 'Preparar versión nueva a partir de esta'
                    }}</Button
                >
                <template v-if="isDraft && props.can.approve">
                    <Input
                        v-model="review.note"
                        class="max-w-xs"
                        placeholder="Comentario de la aprobación (opcional)"
                    />
                    <Button
                        :disabled="
                            review.processing ||
                            form.isDirty ||
                            props.version.missing.length > 0
                        "
                        data-test="approve"
                        @click="doApprove"
                        >Aprobar</Button
                    >
                    <span
                        v-if="form.isDirty"
                        class="text-xs text-muted-foreground"
                        >Guardá los cambios antes de aprobar.</span
                    >
                </template>
                <Button
                    v-if="
                        props.version.status === 'approved' && props.can.approve
                    "
                    data-test="activate"
                    @click="doActivate"
                    >Poner vigente</Button
                >
                <Button
                    v-if="canRetire && !retiring"
                    variant="ghost"
                    @click="retiring = true"
                    >{{ isDraft ? 'Descartar borrador' : 'Retirar' }}</Button
                >
                <template v-if="retiring">
                    <Input
                        v-model="review.reason"
                        class="max-w-sm"
                        placeholder="Motivo"
                    />
                    <Button
                        variant="destructive"
                        :disabled="!review.reason || review.processing"
                        data-test="retire"
                        @click="doRetire"
                        >Confirmar</Button
                    >
                    <Button variant="ghost" @click="retiring = false"
                        >Cancelar</Button
                    >
                </template>
            </div>
            <p
                v-if="retiring && props.version.status === 'active'"
                class="text-sm text-red-700 dark:text-red-300"
            >
                Si retirás la versión vigente sin poner otra, no se van a poder
                recibir muestras de este estudio y tipo de muestra.
            </p>
            <ul v-if="reviewErrors.length" class="text-sm text-red-600">
                <li v-for="e in reviewErrors" :key="e">{{ e }}</li>
            </ul>
        </section>

        <div class="grid gap-4 lg:grid-cols-[13rem_1fr]">
            <!-- Índice de secciones -->
            <nav
                class="top-4 grid h-fit gap-1 self-start rounded-lg border p-2 text-sm lg:sticky"
                aria-label="Secciones del protocolo"
            >
                <a
                    v-for="(s, i) in sections"
                    :key="s.id"
                    :href="`#section-${s.id}`"
                    class="flex items-center gap-2 rounded px-2 py-1 hover:bg-muted"
                >
                    <AlertTriangle
                        v-if="errorsBySection[s.id]?.length"
                        class="size-4 shrink-0 text-red-600"
                    />
                    <CircleDot
                        v-else-if="missingBySection[s.id]?.length"
                        class="size-4 shrink-0 text-amber-600"
                    />
                    <CheckCircle2
                        v-else
                        class="size-4 shrink-0 text-muted-foreground/50"
                    />
                    <span v-if="i" class="text-muted-foreground">{{ i }}.</span>
                    {{ s.title }}
                </a>
                <label
                    v-if="editable"
                    class="mt-2 flex items-center gap-2 border-t px-2 pt-2 text-xs text-muted-foreground"
                >
                    <input
                        v-model="showCodes"
                        type="checkbox"
                        class="size-3.5"
                    />
                    Mostrar códigos internos
                </label>
            </nav>

            <div class="flex min-w-0 flex-col gap-4">
                <p
                    v-if="!editable"
                    class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                >
                    Solo lectura.
                    {{
                        isDraft
                            ? 'Los borradores los edita quien prepara la configuración clínica.'
                            : 'Una versión aprobada no se modifica: los cambios van en una versión nueva.'
                    }}
                </p>
                <div
                    v-if="errorCount"
                    class="rounded-md border border-red-500/50 bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
                    data-test="form-errors"
                >
                    No se guardó: hay {{ errorCount }}
                    {{
                        errorCount === 1 ? 'dato a revisar' : 'datos a revisar'
                    }}
                    en
                    <template
                        v-for="(s, i) in sections.filter(
                            (x) => errorsBySection[x.id]?.length,
                        )"
                        :key="s.id"
                        ><template v-if="i">, </template
                        ><a :href="`#section-${s.id}`" class="underline">{{
                            s.title
                        }}</a></template
                    >.
                </div>

                <!-- Resumen -->
                <ProtocolSection
                    id="summary"
                    :style="{ order: order('summary') }"
                    title="Resumen"
                    description="Así va a recorrer la mesada este protocolo. Se actualiza mientras editás."
                >
                    <ProtocolSummary
                        :content="c"
                        :is-blood="isBlood"
                        :media="props.media"
                        :atmospheres="props.atmospheres"
                        :outcomes="props.outcomes"
                        :ast-panels="props.version.ast_panels"
                        :sample-type="props.version.protocol.sample_type"
                    />
                </ProtocolSection>

                <fieldset :disabled="!editable" class="contents">
                    <!-- Frasco -->
                    <ProtocolSection
                        v-if="isBlood && c.blood_culture_settings"
                        id="blood"
                        :step="order('blood')"
                        :style="{ order: order('blood') }"
                        title="Frasco"
                        description="Cómo se incuba el frasco, qué se hace cuando da positivo y cómo se informa el negativo."
                        :missing="missingBySection.blood"
                        :errors="errorsBySection.blood"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid content-start gap-2">
                                <h3 class="text-sm font-medium">
                                    Tipos de frasco
                                </h3>
                                <p :class="hintClass">
                                    Los que se eligen al cargar el frasco (p.
                                    ej. aerobio, anaerobio, pediátrico).
                                </p>
                                <div
                                    v-for="(t, i) in c.blood_culture_settings
                                        .bottle_types"
                                    :key="i"
                                    class="flex gap-2"
                                >
                                    <Input
                                        v-if="showCodes"
                                        v-model="t.code"
                                        class="w-24"
                                        aria-label="Código"
                                    />
                                    <Input
                                        v-model="t.label"
                                        placeholder="Nombre del frasco"
                                        aria-label="Tipo de frasco"
                                    />
                                    <Button
                                        v-if="editable"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Quitar"
                                        @click="
                                            c.blood_culture_settings.bottle_types.splice(
                                                i,
                                                1,
                                            )
                                        "
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                                <Button
                                    v-if="editable"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-fit"
                                    @click="
                                        c.blood_culture_settings.bottle_types.push(
                                            { code: '', label: '' },
                                        )
                                    "
                                    ><Plus class="size-4" /> Tipo de
                                    frasco</Button
                                >
                            </div>
                            <label class="grid content-start gap-1 text-sm">
                                Equipos
                                <Input
                                    v-model="instruments"
                                    placeholder="Separados por coma"
                                />
                                <span :class="hintClass"
                                    >Los incubadores automatizados del
                                    laboratorio. Sin equipo, el frasco se incuba
                                    en estufa.</span
                                >
                            </label>
                        </div>

                        <div
                            class="grid gap-3 rounded-md bg-muted/40 p-3 text-sm"
                        >
                            <h3 class="font-medium">Incubación</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                En el equipo
                                <input
                                    v-model.number="
                                        c.blood_culture_settings
                                            .incubation_days_automated
                                    "
                                    type="number"
                                    min="1"
                                    :class="numberClass"
                                />
                                días · en estufa (manual)
                                <input
                                    v-model.number="
                                        c.blood_culture_settings
                                            .incubation_days_manual
                                    "
                                    type="number"
                                    min="1"
                                    :class="numberClass"
                                />
                                días.
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                En estufa, repique a ciegas cada
                                <input
                                    v-model.number="
                                        c.blood_culture_settings
                                            .blind_subculture_interval_hours
                                    "
                                    type="number"
                                    min="1"
                                    :class="numberClass"
                                />
                                h en
                                <select
                                    v-model="
                                        c.blood_culture_settings
                                            .blind_subculture_medium_code
                                    "
                                    :class="inputClass"
                                    aria-label="Medio del repique a ciegas"
                                >
                                    <option value="" disabled>Medio…</option>
                                    <option
                                        v-for="m in props.media"
                                        :key="m.id"
                                        :value="m.code"
                                    >
                                        {{ m.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <h3 class="text-sm font-medium">
                                Cuando el frasco da positivo: Gram del frasco
                            </h3>
                            <p :class="hintClass">
                                Las opciones que se eligen al informar el Gram
                                (p. ej. cocos gram positivos en racimos).
                            </p>
                            <div
                                v-for="(t, i) in c.blood_culture_settings
                                    .gram_options"
                                :key="i"
                                class="flex gap-2"
                            >
                                <Input
                                    v-if="showCodes"
                                    v-model="t.code"
                                    class="w-24"
                                    aria-label="Código"
                                />
                                <Input
                                    v-model="t.label"
                                    placeholder="Texto que se informa"
                                    aria-label="Opción del Gram"
                                />
                                <Button
                                    v-if="editable"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Quitar"
                                    @click="
                                        c.blood_culture_settings.gram_options.splice(
                                            i,
                                            1,
                                        )
                                    "
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                            <Button
                                v-if="editable"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="w-fit"
                                @click="
                                    c.blood_culture_settings.gram_options.push({
                                        code: '',
                                        label: '',
                                    })
                                "
                                ><Plus class="size-4" /> Opción del Gram</Button
                            >
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="
                                        c.blood_culture_settings
                                            .communicate_on_positive_gram
                                    "
                                    type="checkbox"
                                    class="size-4"
                                />
                                El Gram de un frasco positivo abre un resultado
                                crítico (para comunicarlo)
                            </label>
                        </div>

                        <label class="grid gap-1 text-sm">
                            Texto del informe negativo
                            <Input
                                v-model="
                                    c.blood_culture_settings
                                        .negative_result_label
                                "
                            />
                            <span :class="hintClass"
                                >Escribí {days} donde va la cantidad de días de
                                incubación: el sistema la completa.</span
                            >
                        </label>
                    </ProtocolSection>

                    <!-- Medios -->
                    <ProtocolSection
                        id="media"
                        :step="order('media')"
                        :style="{ order: order('media') }"
                        :title="sectionTitle('media')"
                        :description="
                            isBlood
                                ? 'Las placas en las que se subcultiva el frasco positivo. Son las que después se leen.'
                                : 'Las placas que se siembran al recibir la muestra (son las que se leen) y, si corresponde, los caldos de enriquecimiento, que no se leen: se repican a placas.'
                        "
                        :missing="missingBySection.media"
                        :errors="errorsBySection.media"
                    >
                        <div
                            v-for="(medium, i) in c.media"
                            :key="i"
                            class="grid gap-2 rounded-md border p-3"
                            :class="{
                                'border-red-500/60': hasError(`media.${i}`),
                                'bg-muted/30': medium.is_enrichment,
                            }"
                            data-test="medium-row"
                        >
                            <div
                                class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                            >
                                <span
                                    >{{
                                        medium.is_enrichment ? 'Caldo' : 'Placa'
                                    }}
                                    {{ i + 1 }}</span
                                >
                                <Button
                                    v-if="editable"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Quitar medio"
                                    @click="c.media.splice(i, 1)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                            <div
                                class="grid gap-2 sm:grid-cols-[1fr_10rem_9rem_10rem]"
                            >
                                <label
                                    class="grid gap-1 text-xs text-muted-foreground"
                                >
                                    Medio
                                    <select
                                        v-model="medium.medium_id"
                                        :class="inputClass"
                                        class="text-foreground"
                                        aria-label="Medio"
                                    >
                                        <option :value="null" disabled>
                                            Elegí el medio…
                                        </option>
                                        <option
                                            v-for="m in props.media"
                                            :key="m.id"
                                            :value="m.id"
                                            :disabled="!m.is_active"
                                        >
                                            {{ m.name }}
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="grid gap-1 text-xs text-muted-foreground"
                                >
                                    Inóculo
                                    <Input
                                        v-model="medium.inoculum"
                                        placeholder="Volumen, ansa, gota…"
                                        aria-label="Inóculo"
                                    />
                                </label>
                                <label
                                    class="grid gap-1 text-xs text-muted-foreground"
                                >
                                    Temperatura
                                    <Input
                                        v-model="medium.incubation_temperature"
                                        aria-label="Temperatura"
                                    />
                                </label>
                                <label
                                    class="grid gap-1 text-xs text-muted-foreground"
                                >
                                    Atmósfera
                                    <select
                                        v-model="medium.atmosphere"
                                        :class="inputClass"
                                        class="text-foreground"
                                        aria-label="Atmósfera"
                                    >
                                        <option
                                            v-for="a in props.atmospheres"
                                            :key="a.value"
                                            :value="a.value"
                                        >
                                            {{ a.label }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <div
                                v-if="!medium.is_enrichment"
                                class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                            >
                                Se lee en las lecturas del cronograma desde las
                                <input
                                    v-model.number="medium.read_from_hours"
                                    type="number"
                                    min="0"
                                    :class="numberClass"
                                    placeholder="—"
                                    aria-label="Se lee desde (h)"
                                />
                                hasta las
                                <input
                                    v-model.number="medium.read_until_hours"
                                    type="number"
                                    min="0"
                                    :class="numberClass"
                                    placeholder="—"
                                    aria-label="Se lee hasta (h)"
                                />
                                h. Vacío: en todas.
                            </div>
                            <div
                                v-if="isBlood && c.blood_culture_settings"
                                class="flex flex-wrap items-center gap-3 text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Para los frascos (ninguno tildado =
                                    todos):</span
                                >
                                <label
                                    v-for="bt in c.blood_culture_settings
                                        .bottle_types"
                                    :key="bt.code"
                                    class="flex items-center gap-1"
                                >
                                    <input
                                        v-model="medium.bottle_types"
                                        type="checkbox"
                                        :value="bt.code"
                                        class="size-4"
                                    />
                                    {{ bt.label }}
                                </label>
                            </div>
                            <label
                                v-if="!isBlood"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="medium.is_enrichment"
                                    type="checkbox"
                                    class="size-4"
                                />
                                Es un caldo de enriquecimiento (no se lee: se
                                repica a placas)
                            </label>
                            <div
                                v-if="medium.is_enrichment"
                                class="grid gap-2 border-l-2 pl-3 text-sm"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    Se repica entre las
                                    <input
                                        v-model.number="
                                            medium.enrichment_subculture_min_hours
                                        "
                                        type="number"
                                        min="0"
                                        :class="numberClass"
                                        aria-label="Repique desde (h)"
                                    />
                                    y las
                                    <input
                                        v-model.number="
                                            medium.enrichment_subculture_due_hours
                                        "
                                        type="number"
                                        min="0"
                                        :class="numberClass"
                                        aria-label="Repique hasta (h)"
                                    />
                                    h desde la siembra, a estas placas:
                                </div>
                                <div
                                    v-for="(
                                        target, j
                                    ) in medium.enrichment_subculture_media"
                                    :key="j"
                                    class="grid gap-2 sm:grid-cols-[1fr_9rem_10rem_auto]"
                                >
                                    <select
                                        v-model="target.medium_code"
                                        :class="inputClass"
                                        aria-label="Medio del repique"
                                    >
                                        <option value="" disabled>
                                            Elegí el medio…
                                        </option>
                                        <option
                                            v-for="m in props.media"
                                            :key="m.id"
                                            :value="m.code"
                                            :disabled="!m.is_active"
                                        >
                                            {{ m.name }}
                                        </option>
                                    </select>
                                    <Input
                                        v-model="target.incubation_temperature"
                                        aria-label="Temperatura"
                                    />
                                    <select
                                        v-model="target.atmosphere"
                                        :class="inputClass"
                                        aria-label="Atmósfera"
                                    >
                                        <option
                                            v-for="a in props.atmospheres"
                                            :key="a.value"
                                            :value="a.value"
                                        >
                                            {{ a.label }}
                                        </option>
                                    </select>
                                    <Button
                                        v-if="editable"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Quitar"
                                        @click="
                                            medium.enrichment_subculture_media.splice(
                                                j,
                                                1,
                                            )
                                        "
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                                <Button
                                    v-if="editable"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-fit"
                                    @click="
                                        medium.enrichment_subculture_media.push(
                                            {
                                                medium_code: '',
                                                incubation_temperature:
                                                    '35 ± 2 °C',
                                                atmosphere: 'aerobic',
                                            },
                                        )
                                    "
                                    ><Plus class="size-4" /> Placa del
                                    repique</Button
                                >
                            </div>
                        </div>
                        <div v-if="editable" class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="c.media.push(blankMedium())"
                                ><Plus class="size-4" /> Agregar placa</Button
                            >
                            <Button
                                v-if="!isBlood"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="c.media.push(blankMedium(true))"
                                ><Plus class="size-4" /> Agregar caldo</Button
                            >
                        </div>
                        <p :class="hintClass">
                            ¿Falta un medio en la lista? Se agrega en Catálogos.
                        </p>
                    </ProtocolSection>

                    <!-- Examen directo -->
                    <ProtocolSection
                        id="direct"
                        :step="order('direct')"
                        :style="{ order: order('direct') }"
                        title="Examen directo"
                        description="Si el estudio lleva un directo (Gram, fresco, etc.), qué se registra en él. Cada campo es una pregunta con sus opciones."
                        :errors="errorsBySection.direct"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <input
                                type="checkbox"
                                class="size-4"
                                :checked="c.direct_exam_settings !== null"
                                data-test="toggle-direct-exam"
                                @change="
                                    toggleDirectExam(
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            Este estudio lleva examen directo
                        </label>
                        <template v-if="c.direct_exam_settings">
                            <div
                                class="grid gap-3 text-sm sm:grid-cols-[16rem_1fr]"
                            >
                                <label class="grid gap-1">
                                    Nombre en la mesada y el informe
                                    <Input
                                        v-model="c.direct_exam_settings.label"
                                    />
                                </label>
                                <div class="grid content-end gap-2">
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="
                                                c.direct_exam_settings.urgent
                                            "
                                            type="checkbox"
                                            class="size-4"
                                        />
                                        Inmediato: se hace apenas se recibe la
                                        muestra (p. ej. LCR)
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="
                                                c.direct_exam_settings
                                                    .critical_if_organisms
                                            "
                                            type="checkbox"
                                            class="size-4"
                                        />
                                        Si se ven microorganismos, abre un
                                        resultado crítico
                                    </label>
                                </div>
                            </div>
                            <div
                                v-for="(field, i) in c.direct_exam_settings
                                    .fields"
                                :key="i"
                                class="grid gap-2 rounded-md border p-3"
                                :class="{
                                    'border-red-500/60': hasError(
                                        `direct_exam_settings.fields.${i}`,
                                    ),
                                }"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Input
                                        v-if="showCodes"
                                        v-model="field.code"
                                        class="w-24"
                                        aria-label="Código del campo"
                                    />
                                    <Input
                                        v-model="field.label"
                                        class="flex-1"
                                        placeholder="Qué se observa (p. ej. Leucocitos, Microorganismos)"
                                        aria-label="Nombre del campo"
                                    />
                                    <select
                                        v-model="field.type"
                                        :class="inputClass"
                                        class="w-40"
                                        aria-label="Tipo de campo"
                                    >
                                        <option :value="undefined">
                                            Opciones
                                        </option>
                                        <option value="options">
                                            Opciones
                                        </option>
                                        <option value="number">Número</option>
                                        <option value="text">Texto</option>
                                    </select>
                                    <Input
                                        v-if="field.type === 'number'"
                                        :model-value="field.unit ?? ''"
                                        @update:model-value="
                                            field.unit = String($event)
                                        "
                                        class="w-24"
                                        placeholder="Unidad"
                                        aria-label="Unidad"
                                    />
                                    <label
                                        v-if="
                                            !field.type ||
                                            field.type === 'options'
                                        "
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="field.multiple"
                                            type="checkbox"
                                            class="size-4"
                                        />
                                        Se puede marcar más de una
                                    </label>
                                    <Button
                                        v-if="editable"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Quitar campo"
                                        @click="
                                            c.direct_exam_settings.fields.splice(
                                                i,
                                                1,
                                            )
                                        "
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                                <p
                                    v-if="
                                        !field.type || field.type === 'options'
                                    "
                                    :class="hintClass"
                                    class="pl-4"
                                >
                                    Opciones:
                                </p>
                                <div
                                    v-for="(option, j) in field.type &&
                                    field.type !== 'options'
                                        ? []
                                        : field.options"
                                    :key="j"
                                    class="flex flex-wrap items-center gap-2 pl-4"
                                >
                                    <Input
                                        v-if="showCodes"
                                        v-model="option.code"
                                        class="w-24"
                                        aria-label="Código de la opción"
                                    />
                                    <Input
                                        v-model="option.label"
                                        class="flex-1"
                                        placeholder="Texto de la opción"
                                        aria-label="Opción"
                                    />
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                        title="Cuenta como hallazgo de microorganismos (para el resultado crítico)"
                                    >
                                        <input
                                            v-model="option.organisms"
                                            type="checkbox"
                                            class="size-4"
                                        />
                                        Indica microorganismos
                                    </label>
                                    <Button
                                        v-if="editable"
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Quitar opción"
                                        @click="field.options.splice(j, 1)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </div>
                                <Button
                                    v-if="
                                        editable &&
                                        (!field.type ||
                                            field.type === 'options')
                                    "
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="ml-4 w-fit"
                                    @click="
                                        field.options.push({
                                            code: '',
                                            label: '',
                                            organisms: false,
                                        })
                                    "
                                    ><Plus class="size-4" /> Opción</Button
                                >
                            </div>
                            <Button
                                v-if="editable"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="w-fit"
                                @click="
                                    c.direct_exam_settings.fields.push({
                                        code: '',
                                        label: '',
                                        multiple: false,
                                        options: [],
                                    })
                                "
                                ><Plus class="size-4" /> Agregar campo</Button
                            >
                        </template>
                    </ProtocolSection>

                    <!-- Lecturas -->
                    <ProtocolSection
                        id="schedule"
                        :step="order('schedule')"
                        :style="{ order: order('schedule') }"
                        title="Lecturas"
                        :description="
                            isBlood
                                ? 'Cuándo se leen las placas del subcultivo. Las horas se cuentan desde que se subcultiva.'
                                : 'Cuándo se leen las placas. Las horas se cuentan desde la siembra: la lectura aparece en la mesada a la primera hora y queda demorada después de la segunda.'
                        "
                        :missing="missingBySection.schedule"
                        :errors="errorsBySection.schedule"
                    >
                        <div
                            v-for="(step, i) in c.reading_schedule"
                            :key="i"
                            class="flex flex-wrap items-center gap-2 text-sm"
                            :class="{
                                'text-red-700 dark:text-red-300': hasError(
                                    `reading_schedule.${i}`,
                                ),
                            }"
                        >
                            <span class="w-20 font-medium"
                                >Lectura {{ i + 1 }}</span
                            >
                            aparece a las
                            <input
                                v-model.number="step.min_hours"
                                type="number"
                                min="0"
                                :class="numberClass"
                                aria-label="Aparece a las (h)"
                            />
                            h y vence a las
                            <input
                                v-model.number="step.due_hours"
                                type="number"
                                min="0"
                                :class="numberClass"
                                aria-label="Vence a las (h)"
                            />
                            h
                            <span :class="hintClass"
                                >≈ {{ hoursLabel(step.min_hours) }} –
                                {{ hoursLabel(step.due_hours) }}</span
                            >
                            <Button
                                v-if="editable"
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Quitar lectura"
                                @click="c.reading_schedule.splice(i, 1)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                        <Button
                            v-if="editable"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-fit"
                            @click="addReading"
                            ><Plus class="size-4" /> Agregar lectura</Button
                        >
                        <!-- Línea de tiempo -->
                        <div
                            v-if="c.reading_schedule.length"
                            class="grid gap-1"
                        >
                            <div class="relative h-6 rounded bg-muted">
                                <div
                                    v-for="(step, i) in c.reading_schedule"
                                    :key="i"
                                    class="absolute top-0 flex h-full items-center justify-center rounded bg-primary/70 text-[10px] text-primary-foreground"
                                    :style="{
                                        left: pct(step.min_hours),
                                        width: `max(1.25rem, calc(${pct(step.due_hours)} - ${pct(step.min_hours)}))`,
                                    }"
                                    :title="`Lectura ${i + 1}`"
                                >
                                    {{ i + 1 }}
                                </div>
                            </div>
                            <div
                                class="flex justify-between text-[10px] text-muted-foreground"
                            >
                                <span
                                    >{{ isBlood ? 'Subcultivo' : 'Siembra' }} (0
                                    h)</span
                                >
                                <span>{{ hoursLabel(timelineEnd) }}</span>
                            </div>
                        </div>
                        <p :class="hintClass">
                            En la última lectura, «sin desarrollo» cierra el
                            cultivo como negativo. En las anteriores, el cultivo
                            espera la siguiente.
                        </p>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="c.allow_bulk_negative_confirmation"
                                type="checkbox"
                                class="size-4"
                            />
                            Permitir confirmar negativos en bloque
                            <span :class="hintClass"
                                >(todavía no tiene efecto en las mesadas)</span
                            >
                        </label>
                    </ProtocolSection>

                    <!-- Opciones de lectura -->
                    <ProtocolSection
                        id="results"
                        :step="order('results')"
                        :style="{ order: order('results') }"
                        title="Qué se informa al leer"
                        description="Las opciones que elige el bacteriólogo en cada lectura. El texto es el que sale en el informe; la acción le dice al sistema qué hacer después."
                        :missing="missingBySection.results"
                        :errors="errorsBySection.results"
                    >
                        <div
                            v-if="c.reading_options.length"
                            class="hidden gap-2 text-xs text-muted-foreground sm:grid"
                            :class="
                                showCodes
                                    ? 'sm:grid-cols-[6rem_1fr_15rem_2.25rem]'
                                    : 'sm:grid-cols-[1fr_15rem_2.25rem]'
                            "
                        >
                            <span v-if="showCodes">Código</span>
                            <span>Texto del informe</span>
                            <span>Acción del sistema</span>
                        </div>
                        <div
                            v-for="(option, i) in c.reading_options"
                            :key="i"
                            class="grid gap-1"
                        >
                            <div
                                class="grid gap-2"
                                :class="
                                    showCodes
                                        ? 'sm:grid-cols-[6rem_1fr_15rem_2.25rem]'
                                        : 'sm:grid-cols-[1fr_15rem_2.25rem]'
                                "
                            >
                                <Input
                                    v-if="showCodes"
                                    v-model="option.code"
                                    aria-label="Código"
                                />
                                <Input
                                    v-model="option.label"
                                    placeholder="p. ej. Sin desarrollo bacteriano"
                                    aria-label="Texto del informe"
                                />
                                <select
                                    v-model="option.outcome"
                                    :class="inputClass"
                                    aria-label="Acción del sistema"
                                >
                                    <option
                                        v-for="o in props.outcomes"
                                        :key="o.value"
                                        :value="o.value"
                                    >
                                        {{ o.label }}
                                    </option>
                                </select>
                                <Button
                                    v-if="editable"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Quitar"
                                    @click="c.reading_options.splice(i, 1)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                            <p :class="hintClass">
                                {{ outcome(option.outcome)?.description }}
                            </p>
                        </div>
                        <Button
                            v-if="editable"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-fit"
                            @click="
                                c.reading_options.push({
                                    code: '',
                                    label: '',
                                    outcome: 'growth',
                                })
                            "
                            ><Plus class="size-4" /> Agregar opción</Button
                        >

                        <div
                            class="grid gap-2 rounded-md bg-muted/40 p-3 text-sm"
                        >
                            <h3 class="font-medium">
                                Textos de las lecturas sin desarrollo
                            </h3>
                            <label class="grid gap-1">
                                Lectura intermedia sin desarrollo (todavía
                                quedan lecturas)
                                <Input
                                    :model-value="
                                        c.intermediate_no_growth_label ?? ''
                                    "
                                    @update:model-value="
                                        c.intermediate_no_growth_label =
                                            String($event)
                                    "
                                    placeholder="Sin desarrollo hasta el momento"
                                />
                            </label>
                            <label class="grid gap-1">
                                Sin desarrollo nuevo, después de una lectura con
                                desarrollo
                                <Input
                                    :model-value="c.no_new_growth_label ?? ''"
                                    @update:model-value="
                                        c.no_new_growth_label = String($event)
                                    "
                                    placeholder="Sin desarrollo nuevo"
                                />
                            </label>
                            <p :class="hintClass">
                                Así el historial no muestra el negativo final en
                                una lectura que no lo es. Vacíos, se usa el
                                texto de ejemplo.
                            </p>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="c.report_non_significant"
                                    type="checkbox"
                                    class="size-4"
                                />
                                El informe incluye los aislamientos no
                                significativos y los probables contaminantes
                            </label>
                        </div>

                        <div
                            class="grid gap-2 rounded-md bg-muted/40 p-3 text-sm"
                            :class="{ 'opacity-60': !hasReincubate }"
                        >
                            <h3 class="font-medium">Reincubación</h3>
                            <div class="flex flex-wrap items-center gap-2">
                                Al elegir una opción «Reincubar», la lectura se
                                corre
                                <input
                                    v-model.number="c.reincubation_hours"
                                    type="number"
                                    min="1"
                                    :class="numberClass"
                                    aria-label="Horas de reincubación"
                                />
                                h. Se puede reincubar hasta
                                <input
                                    v-model.number="c.max_reincubations"
                                    type="number"
                                    min="0"
                                    :class="numberClass"
                                    aria-label="Reincubaciones máximas"
                                />
                                veces.
                            </div>
                            <p v-if="!hasReincubate" :class="hintClass">
                                Solo se usa si hay una opción con la acción
                                «Reincubar».
                            </p>
                        </div>
                    </ProtocolSection>

                    <!-- Recuentos -->
                    <ProtocolSection
                        id="counts"
                        :step="order('counts')"
                        :style="{ order: order('counts') }"
                        title="Recuentos"
                        description="Las categorías que elige el bacteriólogo al cargar cada aislamiento (p. ej. rangos de UFC/mL). El sistema no decide la significancia: la decide quien lee."
                        :errors="errorsBySection.counts"
                    >
                        <div
                            v-for="(category, i) in c.count_categories"
                            :key="i"
                            class="flex gap-2"
                        >
                            <Input
                                v-if="showCodes"
                                v-model="category.code"
                                class="w-24"
                                aria-label="Código"
                            />
                            <Input
                                v-model="category.label"
                                placeholder="Texto de la categoría"
                                aria-label="Categoría de recuento"
                            />
                            <Button
                                v-if="editable"
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Quitar"
                                @click="c.count_categories.splice(i, 1)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                        <p v-if="!c.count_categories.length" :class="hintClass">
                            Sin categorías: los aislamientos se cargan sin
                            recuento.
                        </p>
                        <Button
                            v-if="editable"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-fit"
                            @click="
                                c.count_categories.push({ code: '', label: '' })
                            "
                            ><Plus class="size-4" /> Agregar categoría</Button
                        >
                    </ProtocolSection>

                    <!-- Antibiograma -->
                    <ProtocolSection
                        id="ast"
                        :step="order('ast')"
                        :style="{ order: order('ast') }"
                        title="Antibiograma"
                        description="Cuándo se lee el antibiograma. Las horas se cuentan desde que se monta; antes de la primera, el sistema no deja cargar la lectura."
                        :missing="missingBySection.ast"
                        :errors="errorsBySection.ast"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            Aparece a las
                            <input
                                v-model.number="c.ast_read_min_hours"
                                type="number"
                                min="0"
                                :class="numberClass"
                                aria-label="Antibiograma: aparece a las (h)"
                            />
                            h y vence a las
                            <input
                                v-model.number="c.ast_read_due_hours"
                                type="number"
                                min="0"
                                :class="numberClass"
                                aria-label="Antibiograma: vence a las (h)"
                            />
                            h de montado.
                        </div>
                        <p :class="hintClass">
                            Un panel puede tener su propia incubación (por
                            ejemplo, el VITEK): se configura en el panel. Los
                            antibióticos de cada panel y los puntos de corte se
                            configuran aparte.
                        </p>
                        <label class="flex items-start gap-2 text-sm">
                            <input
                                v-model="c.allow_presumptive_ast"
                                type="checkbox"
                                class="mt-0.5 size-4"
                            />
                            <span
                                >Se puede preparar el antibiograma con una
                                identificación presuntiva (por ejemplo, tarjetas
                                VITEK cargadas juntas, o difusión montada con el
                                Gram y las pruebas rápidas). Se lee recién con
                                la identificación definitiva.</span
                            >
                        </label>
                    </ProtocolSection>

                    <!-- Notas -->
                    <ProtocolSection
                        id="notes"
                        :step="order('notes')"
                        :style="{ order: order('notes') }"
                        title="Fuente y notas"
                        description="De dónde sale el protocolo y qué cambió en esta versión. Queda en el historial de aprobación."
                        :errors="errorsBySection.notes"
                    >
                        <label class="grid gap-1 text-sm">
                            Fuente (bibliografía, procedimiento interno)
                            <textarea
                                v-model="c.source"
                                rows="2"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="grid gap-1 text-sm">
                            Qué cambia en esta versión
                            <textarea
                                v-model="c.notes"
                                rows="2"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </label>
                    </ProtocolSection>
                </fieldset>
            </div>
        </div>

        <div
            v-if="editable"
            class="sticky bottom-0 flex items-center justify-end gap-3 border-t bg-background/95 py-3"
        >
            <span
                v-if="props.version.missing.length && !form.isDirty"
                class="text-sm text-amber-700 dark:text-amber-300"
                >Faltan {{ props.version.missing.length }} datos para poder
                aprobarla.</span
            >
            <span v-if="form.isDirty" class="text-sm text-muted-foreground"
                >Cambios sin guardar. Lo que falta para aprobar se recalcula al
                guardar.</span
            >
            <Button
                size="lg"
                :disabled="form.processing"
                data-test="save-draft"
                @click="save"
            >
                <Spinner v-if="form.processing" /> Guardar borrador
            </Button>
        </div>
    </div>
</template>
