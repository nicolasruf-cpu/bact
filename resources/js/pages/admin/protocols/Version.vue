<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { statusClass } from '@/lib/configStatus';
import { formatDateTime } from '@/lib/datetime';
import {
    activate,
    approve,
    draft,
    retire,
    show,
    update,
} from '@/routes/admin/protocol-versions';
import { index as protocolsIndex } from '@/routes/admin/protocols';

type CodeLabel = { code: string; label: string };
type Option = CodeLabel & { outcome: string };
type Target = {
    medium_code: string;
    incubation_temperature: string;
    atmosphere: string;
};
type MediumRow = {
    medium_id: number | null;
    inoculum: string;
    incubation_temperature: string;
    atmosphere: string;
    is_enrichment: boolean;
    enrichment_subculture_min_hours: number | null;
    enrichment_subculture_due_hours: number | null;
    enrichment_subculture_media: Target[];
};
type DirectField = CodeLabel & {
    multiple: boolean;
    options: (CodeLabel & { organisms: boolean })[];
};
type DirectExam = {
    label: string;
    urgent: boolean;
    critical_if_organisms: boolean;
    fields: DirectField[];
};
type BloodSettings = {
    incubation_days_automated: number;
    incubation_days_manual: number;
    blind_subculture_interval_hours: number;
    blind_subculture_medium_code: string;
    instruments: string[];
    bottle_types: CodeLabel[];
    gram_options: CodeLabel[];
    negative_result_label: string;
    communicate_on_positive_gram: boolean;
};
type Content = {
    notes: string | null;
    source: string | null;
    reading_schedule: { min_hours: number; due_hours: number }[];
    reincubation_hours: number | null;
    max_reincubations: number;
    allow_bulk_negative_confirmation: boolean;
    reading_options: Option[];
    count_categories: CodeLabel[];
    ast_read_min_hours: number | null;
    ast_read_due_hours: number | null;
    media: MediumRow[];
    direct_exam_settings: DirectExam | null;
    blood_culture_settings: BloodSettings | null;
};

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
        missing: string[];
        content: Content;
        siblings: {
            id: number;
            version: number;
            status: string;
            status_label: string;
        }[];
    };
    media: { id: number; code: string; name: string; is_active: boolean }[];
    atmospheres: { value: string; label: string }[];
    outcomes: { value: string; label: string }[];
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
    content: JSON.parse(JSON.stringify(props.version.content)) as Content,
    lock_version: props.version.lock_version,
});
const c = form.content;

const errorList = computed(() =>
    Object.entries(form.errors as Record<string, string>).map(
        ([key, message]) => ({ key, message }),
    ),
);

function save() {
    form.put(update(props.version.id).url, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
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

const blankMedium = (): MediumRow => ({
    medium_id: null,
    inoculum: '',
    incubation_temperature: '35 ± 2 °C',
    atmosphere: 'aerobic',
    is_enrichment: false,
    enrichment_subculture_min_hours: null,
    enrichment_subculture_due_hours: null,
    enrichment_subculture_media: [],
});

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

// Revisión.
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

const canRetire = computed(
    () =>
        props.version.status !== 'retired' &&
        (props.can.approve || (isDraft.value && props.can.draft)),
);
const reviewErrors = computed(() =>
    Object.values(review.errors as Record<string, string>),
);
const inputClass = 'h-9 rounded-md border bg-background px-2 text-sm';
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
                <Badge v-if="isBlood" variant="outline"
                    >Frasco de hemocultivo</Badge
                >
            </div>
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
            <nav class="flex flex-wrap gap-1 text-xs">
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

            <div
                v-if="isDraft && props.version.missing.length"
                class="rounded-md border border-dashed p-3 text-sm"
                data-test="missing"
            >
                <p class="font-medium">Para aprobarla falta:</p>
                <ul class="list-disc pl-5">
                    <li v-for="m in props.version.missing" :key="m">{{ m }}</li>
                </ul>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <Button
                    v-if="!isDraft && props.can.draft"
                    variant="outline"
                    data-test="new-draft"
                    @click="newDraft"
                    >Preparar versión nueva a partir de esta</Button
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

        <div
            v-if="errorList.length"
            class="rounded-md border border-red-500/50 bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
            data-test="form-errors"
        >
            <p class="font-medium">Revisá estos datos:</p>
            <ul class="list-disc pl-5">
                <li v-for="e in errorList" :key="e.key">{{ e.message }}</li>
            </ul>
        </div>

        <fieldset :disabled="!editable" class="grid gap-4">
            <!-- Lecturas -->
            <section class="grid gap-3 rounded-lg border p-4">
                <h2 class="font-medium">Lecturas</h2>
                <p class="text-sm text-muted-foreground">
                    Horas contadas desde la siembra. Cada lectura está
                    disponible desde la primera hora y vence en la segunda.
                </p>
                <div
                    v-for="(step, i) in c.reading_schedule"
                    :key="i"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="w-20">Lectura {{ i + 1 }}</span>
                    desde
                    <input
                        v-model.number="step.min_hours"
                        type="number"
                        min="0"
                        :class="inputClass"
                        class="w-20"
                    />
                    hasta
                    <input
                        v-model.number="step.due_hours"
                        type="number"
                        min="0"
                        :class="inputClass"
                        class="w-20"
                    />
                    h
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
                    @click="
                        c.reading_schedule.push({ min_hours: 0, due_hours: 0 })
                    "
                    ><Plus class="size-4" /> Agregar lectura</Button
                >
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="grid gap-1">
                        Reincubación (h)
                        <input
                            v-model.number="c.reincubation_hours"
                            type="number"
                            min="1"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        Reincubaciones máximas
                        <input
                            v-model.number="c.max_reincubations"
                            type="number"
                            min="0"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        Antibiograma: lectura desde (h)
                        <input
                            v-model.number="c.ast_read_min_hours"
                            type="number"
                            min="0"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        hasta (h)
                        <input
                            v-model.number="c.ast_read_due_hours"
                            type="number"
                            min="0"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="flex items-center gap-2 self-end">
                        <input
                            v-model="c.allow_bulk_negative_confirmation"
                            type="checkbox"
                            class="size-4"
                        />
                        Permite confirmar negativos en bloque
                    </label>
                </div>
            </section>

            <!-- Opciones de lectura -->
            <section class="grid gap-3 rounded-lg border p-4">
                <h2 class="font-medium">Opciones de lectura</h2>
                <p class="text-sm text-muted-foreground">
                    El texto es el que sale en el informe; el resultado le dice
                    al sistema qué hacer (cargar aislamientos, cerrar negativo,
                    reincubar).
                </p>
                <div
                    v-for="(option, i) in c.reading_options"
                    :key="i"
                    class="grid gap-2 sm:grid-cols-[7rem_1fr_14rem_auto]"
                >
                    <Input
                        v-model="option.code"
                        placeholder="Código"
                        aria-label="Código"
                    />
                    <Input
                        v-model="option.label"
                        placeholder="Texto"
                        aria-label="Texto"
                    />
                    <select
                        v-model="option.outcome"
                        :class="inputClass"
                        aria-label="Resultado"
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
            </section>

            <!-- Recuentos -->
            <section class="grid gap-3 rounded-lg border p-4">
                <h2 class="font-medium">Categorías de recuento</h2>
                <p class="text-sm text-muted-foreground">
                    Las elige el bacteriólogo al cargar un aislamiento. El
                    sistema no aplica umbrales de significancia.
                </p>
                <div
                    v-for="(category, i) in c.count_categories"
                    :key="i"
                    class="grid gap-2 sm:grid-cols-[7rem_1fr_auto]"
                >
                    <Input
                        v-model="category.code"
                        placeholder="Código"
                        aria-label="Código"
                    />
                    <Input
                        v-model="category.label"
                        placeholder="Texto"
                        aria-label="Texto"
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
                <Button
                    v-if="editable"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="w-fit"
                    @click="c.count_categories.push({ code: '', label: '' })"
                    ><Plus class="size-4" /> Agregar categoría</Button
                >
            </section>

            <!-- Medios -->
            <section class="grid gap-3 rounded-lg border p-4">
                <h2 class="font-medium">Medios a sembrar</h2>
                <div
                    v-for="(medium, i) in c.media"
                    :key="i"
                    class="grid gap-2 rounded-md border p-3"
                    data-test="medium-row"
                >
                    <div
                        class="grid gap-2 sm:grid-cols-[1fr_10rem_9rem_10rem_auto]"
                    >
                        <select
                            v-model="medium.medium_id"
                            :class="inputClass"
                            aria-label="Medio"
                        >
                            <option :value="null" disabled>Medio…</option>
                            <option
                                v-for="m in props.media"
                                :key="m.id"
                                :value="m.id"
                                :disabled="!m.is_active"
                            >
                                {{ m.name }} ({{ m.code }})
                            </option>
                        </select>
                        <Input
                            v-model="medium.inoculum"
                            placeholder="Inóculo"
                            aria-label="Inóculo"
                        />
                        <Input
                            v-model="medium.incubation_temperature"
                            placeholder="Temperatura"
                            aria-label="Temperatura"
                        />
                        <select
                            v-model="medium.atmosphere"
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
                            aria-label="Quitar medio"
                            @click="c.media.splice(i, 1)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="medium.is_enrichment"
                            type="checkbox"
                            class="size-4"
                        />
                        Caldo de enriquecimiento (no se lee: se repica a placas)
                    </label>
                    <div
                        v-if="medium.is_enrichment"
                        class="grid gap-2 border-l-2 pl-3 text-sm"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            Repique desde
                            <input
                                v-model.number="
                                    medium.enrichment_subculture_min_hours
                                "
                                type="number"
                                min="0"
                                :class="inputClass"
                                class="w-20"
                            />
                            hasta
                            <input
                                v-model.number="
                                    medium.enrichment_subculture_due_hours
                                "
                                type="number"
                                min="0"
                                :class="inputClass"
                                class="w-20"
                            />
                            h desde la siembra, a:
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
                                <option
                                    v-for="m in props.media"
                                    :key="m.id"
                                    :value="m.code"
                                    :disabled="!m.is_active"
                                >
                                    {{ m.name }} ({{ m.code }})
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
                                medium.enrichment_subculture_media.push({
                                    medium_code: '',
                                    incubation_temperature: '35 ± 2 °C',
                                    atmosphere: 'aerobic',
                                })
                            "
                            ><Plus class="size-4" /> Medio del repique</Button
                        >
                    </div>
                </div>
                <Button
                    v-if="editable"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="w-fit"
                    @click="c.media.push(blankMedium())"
                    ><Plus class="size-4" /> Agregar medio</Button
                >
            </section>

            <!-- Examen directo -->
            <section class="grid gap-3 rounded-lg border p-4">
                <label class="flex items-center gap-2 font-medium">
                    <input
                        type="checkbox"
                        class="size-4"
                        :checked="c.direct_exam_settings !== null"
                        data-test="toggle-direct-exam"
                        @change="
                            toggleDirectExam(
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    Lleva examen directo
                </label>
                <template v-if="c.direct_exam_settings">
                    <div class="flex flex-wrap items-end gap-4 text-sm">
                        <label class="grid gap-1">
                            Nombre
                            <Input v-model="c.direct_exam_settings.label" />
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="c.direct_exam_settings.urgent"
                                type="checkbox"
                                class="size-4"
                            />
                            Inmediato
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="
                                    c.direct_exam_settings.critical_if_organisms
                                "
                                type="checkbox"
                                class="size-4"
                            />
                            Con microorganismos abre un resultado crítico
                        </label>
                    </div>
                    <div
                        v-for="(field, i) in c.direct_exam_settings.fields"
                        :key="i"
                        class="grid gap-2 rounded-md border p-3"
                    >
                        <div
                            class="grid gap-2 sm:grid-cols-[7rem_1fr_auto_auto]"
                        >
                            <Input
                                v-model="field.code"
                                placeholder="Código"
                                aria-label="Código del campo"
                            />
                            <Input
                                v-model="field.label"
                                placeholder="Campo (p. ej. Leucocitos)"
                                aria-label="Nombre del campo"
                            />
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="field.multiple"
                                    type="checkbox"
                                    class="size-4"
                                />
                                Varias opciones
                            </label>
                            <Button
                                v-if="editable"
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Quitar campo"
                                @click="
                                    c.direct_exam_settings.fields.splice(i, 1)
                                "
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                        <div
                            v-for="(option, j) in field.options"
                            :key="j"
                            class="grid gap-2 pl-4 sm:grid-cols-[7rem_1fr_auto_auto]"
                        >
                            <Input
                                v-model="option.code"
                                placeholder="Código"
                                aria-label="Código de la opción"
                            />
                            <Input
                                v-model="option.label"
                                placeholder="Opción"
                                aria-label="Opción"
                            />
                            <label class="flex items-center gap-2 text-sm">
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
                            v-if="editable"
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
            </section>

            <!-- Frasco -->
            <section
                v-if="isBlood && c.blood_culture_settings"
                class="grid gap-3 rounded-lg border p-4"
            >
                <h2 class="font-medium">Frasco de hemocultivo</h2>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="grid gap-1">
                        Días de incubación (equipo)
                        <input
                            v-model.number="
                                c.blood_culture_settings
                                    .incubation_days_automated
                            "
                            type="number"
                            min="1"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        Días de incubación (manual)
                        <input
                            v-model.number="
                                c.blood_culture_settings.incubation_days_manual
                            "
                            type="number"
                            min="1"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        Repique a ciegas cada (h)
                        <input
                            v-model.number="
                                c.blood_culture_settings
                                    .blind_subculture_interval_hours
                            "
                            type="number"
                            min="1"
                            :class="inputClass"
                            class="w-24"
                        />
                    </label>
                    <label class="grid gap-1">
                        Medio del repique a ciegas
                        <select
                            v-model="
                                c.blood_culture_settings
                                    .blind_subculture_medium_code
                            "
                            :class="inputClass"
                        >
                            <option
                                v-for="m in props.media"
                                :key="m.id"
                                :value="m.code"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                    </label>
                    <label class="grid flex-1 gap-1">
                        Equipos (separados por coma)
                        <Input v-model="instruments" />
                    </label>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <h3 class="text-sm font-medium">Tipos de frasco</h3>
                        <div
                            v-for="(t, i) in c.blood_culture_settings
                                .bottle_types"
                            :key="i"
                            class="grid grid-cols-[6rem_1fr_auto] gap-2"
                        >
                            <Input v-model="t.code" aria-label="Código" />
                            <Input v-model="t.label" aria-label="Texto" />
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
                                c.blood_culture_settings.bottle_types.push({
                                    code: '',
                                    label: '',
                                })
                            "
                            ><Plus class="size-4" /> Tipo</Button
                        >
                    </div>
                    <div class="grid content-start gap-2">
                        <h3 class="text-sm font-medium">
                            Opciones del Gram del frasco
                        </h3>
                        <div
                            v-for="(t, i) in c.blood_culture_settings
                                .gram_options"
                            :key="i"
                            class="grid grid-cols-[6rem_1fr_auto] gap-2"
                        >
                            <Input v-model="t.code" aria-label="Código" />
                            <Input v-model="t.label" aria-label="Texto" />
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
                            ><Plus class="size-4" /> Opción</Button
                        >
                    </div>
                </div>
                <label class="grid gap-1 text-sm">
                    Texto del negativo ({days} = días de incubación)
                    <Input
                        v-model="c.blood_culture_settings.negative_result_label"
                    />
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="
                            c.blood_culture_settings
                                .communicate_on_positive_gram
                        "
                        type="checkbox"
                        class="size-4"
                    />
                    El Gram de un frasco positivo abre un resultado crítico
                </label>
            </section>

            <!-- Notas -->
            <section class="grid gap-3 rounded-lg border p-4">
                <h2 class="font-medium">Fuente y notas</h2>
                <label class="grid gap-1 text-sm">
                    Fuente (bibliografía, procedimiento interno)
                    <textarea
                        v-model="c.source"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Notas de esta versión
                    <textarea
                        v-model="c.notes"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </label>
            </section>
        </fieldset>

        <div
            v-if="editable"
            class="sticky bottom-0 flex justify-end gap-2 border-t bg-background/95 py-3"
        >
            <span
                v-if="form.isDirty"
                class="self-center text-sm text-muted-foreground"
                >Cambios sin guardar</span
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
