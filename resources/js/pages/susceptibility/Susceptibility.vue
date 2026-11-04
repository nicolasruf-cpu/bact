<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FlaskConical, Pill, Route } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DateTimeInput from '@/components/DateTimeInput.vue';
import AdditionalAntibiotics from '@/components/domain/AdditionalAntibiotics.vue';
import type { AdditionalRow } from '@/components/domain/AdditionalAntibiotics.vue';
import EarlyTimeWarning from '@/components/domain/EarlyTimeWarning.vue';
import InterpretationBadge from '@/components/domain/InterpretationBadge.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { create as identifyRoute } from '@/routes/identifications';
import {
    cancel as cancelRoute,
    read as readRoute,
    setup as setupRoute,
    tests as testsRoute,
} from '@/routes/susceptibility';
import type { SampleHeader } from '@/types/clinical';

type Result = {
    antibiotic_id: number;
    antibiotic: string;
    code: string;
    method: string;
    unit: string;
    is_additional: boolean;
    addition_reason: string | null;
    read: boolean;
    mic_operator: string | null;
    value: number | null;
    automatic: string | null;
    reviewed: string | null;
    final: string | null;
    override_reason: string | null;
    is_reported: boolean;
    reported_as: string | null;
    rules: { effect: string; note: string; interpretation: string | null }[];
    rule_notes: string | null;
};
type CodeLabel = { code: string; label: string };

const props = defineProps<{
    sample: SampleHeader;
    isolate: {
        ulid: string;
        number: number;
        organism: string | null;
        group: string | null;
        count_category: string | null;
        presumptive: boolean;
        status: string;
    };
    allowPresumptive: boolean;
    mechanismTests: {
        id: number;
        name: string;
        kind_label: string;
        options: CodeLabel[];
        incubation_hours: number | null;
    }[];
    isolateTests: {
        id: number;
        name: string;
        kind_label: string;
        result: string | null;
        read_at: string | null;
        readable_at: string;
        options: CodeLabel[];
    }[];
    cancelled: {
        panel: string;
        set_up_at: string;
        cancelled_at: string | null;
        reason: string | null;
    }[];
    panels: {
        id: number;
        code: string;
        name: string;
        is_vitek: boolean;
        is_reference_template: boolean;
        antibiotic_ids: number[];
        read_min_hours: number | null;
    }[];
    antibiotics: { id: number; code: string; name: string }[];
    methods: { value: string; label: string; unit: string }[];
    test: {
        ulid: string;
        status: string;
        method: string;
        unit: string;
        panel: string;
        is_vitek: boolean;
        set_up_at: string;
        available_at: string;
        panel_covers_group: boolean;
        results: Result[];
        /** Por "antibiótico|método". */
        breakpoints: Record<string, { s: number; r: number }>;
    } | null;
    breakpointSet: {
        name: string;
        edition: string;
        is_synthetic: boolean;
    } | null;
    can: { setup: boolean; read: boolean; override: boolean; tests: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mesada de antibiogramas',
                href: '/workbenches/susceptibility',
            },
            { title: 'Antibiograma', href: '#' },
        ],
    },
});

// --- Preparación -----------------------------------------------------------
const setupForm = useForm({
    method: props.panels[0]?.is_vitek ? 'mic' : 'disk',
    panel_id: props.panels[0]?.id ?? null,
    set_up_at: null as string | null,
    additional: [] as AdditionalRow[],
});
// El panel VITEK se carga como CIM (D-0021).
const selectedPanel = computed(() =>
    props.panels.find((p) => p.id === setupForm.panel_id),
);
watch(selectedPanel, (panel) => {
    if (panel?.is_vitek) {
        setupForm.method = 'mic';
    }
});
function setUp() {
    setupForm
        .transform((data) => ({
            ...data,
            additional: cleanAdditional(data.additional),
        }))
        .post(setupRoute(props.isolate.ulid).url);
}

// --- Descarte (A1: presuntiva que no se confirmó) -------------------------
const cancelling = ref(false);
const cancelForm = useForm({ cancellation_reason: '' });
function cancelTest() {
    cancelForm.post(cancelRoute(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            cancelling.value = false;
        },
    });
}

// --- Pruebas de mecanismo (B5) --------------------------------------------
const mechanismForm = useForm({
    tests: [] as { test_id: number | null; result_code: string }[],
});
function saveMechanism() {
    mechanismForm
        .transform((data) => ({
            tests: data.tests.filter((t) => t.test_id !== null),
        }))
        .post(testsRoute(props.isolate.ulid).url, {
            preserveScroll: true,
            onSuccess: () => mechanismForm.reset(),
        });
}
const mechanismOptions = (id: number | null) =>
    props.mechanismTests.find((t) => t.id === id);

function rulesFor(id: number) {
    return props.test?.results.find((r) => r.antibiotic_id === id)?.rules ?? [];
}

function cleanAdditional(rows: AdditionalRow[]) {
    return rows.map((r) => ({ ...r, reason: r.reason.trim() || null }));
}

function additionalError(errors: Record<string, string>): string | undefined {
    return (
        errors.additional ??
        Object.entries(errors).find(([k]) => k.startsWith('additional.'))?.[1]
    );
}

// --- Lectura ---------------------------------------------------------------
type Row = {
    antibiotic_id: number;
    method: string;
    mic_operator: string;
    value: number | null;
    reviewed: string | null;
    override_reason: string;
    is_reported: boolean;
};
// Solo los antibióticos que faltan leer (D-0022).
const pending = computed(() =>
    (props.test?.results ?? []).filter((r) => !r.read),
);
const alreadyRead = computed(() =>
    (props.test?.results ?? []).filter((r) => r.read),
);
const readForm = useForm({
    results: pending.value.map<Row>((r) => ({
        antibiotic_id: r.antibiotic_id,
        method: r.method,
        mic_operator: r.mic_operator ?? '=',
        value: r.value,
        reviewed: null,
        override_reason: '',
        is_reported: r.is_reported,
    })),
    early_confirmed: false,
    early_reason: '',
    additional: [] as AdditionalRow[],
});

const isMic = (row: { method: string }) => row.method === 'mic';
const unitOf = (method: string) =>
    props.methods.find((m) => m.value === method)?.unit ?? '';
const readable = computed(
    () =>
        props.test !== null && new Date(props.test.available_at) <= new Date(),
);

/**
 * Vista previa de la interpretación automática, con la misma regla que el servidor
 * (BreakpointInterpreter). Es solo orientativa: el servidor recalcula y es la fuente de verdad.
 */
function preview(row: Row): string | null {
    const bp = props.test?.breakpoints[`${row.antibiotic_id}|${row.method}`];
    if (
        !bp ||
        row.value === null ||
        row.value === undefined ||
        Number.isNaN(Number(row.value))
    ) {
        return null;
    }
    const v = Number(row.value);
    if (isMic(row)) {
        return v <= bp.s ? 'S' : v >= bp.r ? 'R' : 'I';
    }

    return v >= bp.s ? 'S' : v <= bp.r ? 'R' : 'I';
}

function needsReason(row: Row): boolean {
    const auto = preview(row);

    return auto !== null && row.reviewed !== null && row.reviewed !== auto;
}

function antibioticName(id: number): string {
    return (
        props.test?.results.find((r) => r.antibiotic_id === id)?.antibiotic ??
        ''
    );
}

function isAdditional(id: number): boolean {
    return (
        props.test?.results.find((r) => r.antibiotic_id === id)
            ?.is_additional ?? false
    );
}

function errorFor(index: number, field: string): string | undefined {
    return (readForm.errors as Record<string, string>)[
        `results.${index}.${field}`
    ];
}

function read() {
    readForm
        .transform((data) => ({
            early_confirmed: data.early_confirmed,
            early_reason: data.early_reason,
            additional: cleanAdditional(data.additional),
            results: data.results.map((r) => ({
                ...r,
                mic_operator: isMic(r) ? r.mic_operator : null,
                reviewed: r.reviewed || null,
                override_reason: r.override_reason || null,
            })),
        }))
        .post(readRoute(props.isolate.ulid).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Antibiograma ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="flex flex-wrap items-center gap-2">
            <h1 class="flex items-center gap-2 text-xl font-semibold">
                <Pill class="size-5" /> Aislamiento {{ props.isolate.number }}:
                <em>{{
                    props.isolate.organism ?? 'sin identificación definitiva'
                }}</em>
            </h1>
            <span class="text-sm text-muted-foreground">{{
                props.isolate.group
            }}</span>
            <Link
                :href="identifyRoute(props.isolate.ulid).url"
                class="ml-auto flex items-center gap-1 text-sm underline"
                ><Route class="size-4" /> Identificación y pruebas</Link
            >
        </div>

        <div
            v-if="props.isolate.presumptive"
            class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            data-test="presumptive-notice"
        >
            Identificación presuntiva: el antibiograma se prepara ahora y se lee
            cuando se confirme la identificación definitiva (los puntos de corte
            dependen del germen).
        </div>
        <div
            v-if="props.test && !props.test.panel_covers_group"
            class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Revisá: el panel {{ props.test.panel }} no es para
            {{ props.isolate.group }}. Si no corresponde, descartalo y prepará
            otro.
        </div>
        <p
            v-for="(c, i) in props.cancelled"
            :key="i"
            class="text-xs text-muted-foreground"
        >
            Descartado: {{ c.panel }} (preparado
            {{ formatDateTime(c.set_up_at) }}) — {{ c.reason }}
        </p>

        <div
            v-if="props.breakpointSet?.is_synthetic"
            class="rounded-md border border-red-500/50 bg-red-50 p-3 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-200"
        >
            Puntos de corte FICTICIOS de demostración ({{
                props.breakpointSet.name
            }}): la interpretación no tiene valor clínico.
        </div>
        <div
            v-else-if="!props.breakpointSet"
            class="rounded-md border p-3 text-sm text-muted-foreground"
        >
            No hay puntos de corte vigentes cargados: la interpretación la
            ingresa el bacteriólogo.
        </div>

        <!-- Preparación -->
        <form
            v-if="!props.test"
            class="grid max-w-xl gap-4 rounded-lg border p-4"
            @submit.prevent="setUp"
        >
            <h2 class="font-medium">Preparar antibiograma</h2>
            <div class="grid gap-1">
                <Label for="panel">Panel</Label>
                <select
                    id="panel"
                    v-model="setupForm.panel_id"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option
                        v-for="panel in props.panels"
                        :key="panel.id"
                        :value="panel.id"
                    >
                        {{ panel.name }}{{ panel.is_vitek ? ' · VITEK' : ''
                        }}{{
                            panel.is_reference_template
                                ? ' (plantilla de referencia)'
                                : ''
                        }}
                    </option>
                </select>
                <p
                    v-if="props.panels.length > 1"
                    class="text-xs text-muted-foreground"
                >
                    Se propone el panel definido para esta muestra; podés elegir
                    otro de los que valen para el germen.
                </p>
                <InputError :message="setupForm.errors.panel_id" />
            </div>
            <div class="grid gap-1">
                <Label for="method">Método</Label>
                <select
                    id="method"
                    v-model="setupForm.method"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    :disabled="selectedPanel?.is_vitek"
                >
                    <option
                        v-for="method in props.methods"
                        :key="method.value"
                        :value="method.value"
                    >
                        {{ method.label }}
                    </option>
                </select>
                <p
                    v-if="selectedPanel?.is_vitek"
                    class="text-xs text-muted-foreground"
                >
                    Panel VITEK: los resultados se cargan como CIM.
                </p>
                <InputError :message="setupForm.errors.method" />
            </div>
            <div class="grid gap-1">
                <Label for="set_up_at">Hora de preparación</Label>
                <DateTimeInput id="set_up_at" v-model="setupForm.set_up_at" />
                <p class="text-xs text-muted-foreground">
                    Vacía: ahora. Si se montó antes de registrarlo, poné la hora
                    real.
                    <template
                        v-if="
                            selectedPanel?.read_min_hours !== null &&
                            selectedPanel?.read_min_hours !== undefined
                        "
                    >
                        Este panel se lee desde las
                        {{ selectedPanel.read_min_hours }} h.</template
                    >
                </p>
                <InputError :message="setupForm.errors.set_up_at" />
            </div>
            <AdditionalAntibiotics
                v-model="setupForm.additional"
                :antibiotics="props.antibiotics"
                :methods="props.methods"
                :exclude="selectedPanel?.antibiotic_ids ?? []"
                hint="Para casos especiales: se preparan junto con el panel y se leen en la misma lectura."
                :error="
                    additionalError(setupForm.errors as Record<string, string>)
                "
            />
            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="!props.can.setup || setupForm.processing"
                    data-test="setup-ast"
                >
                    <Spinner v-if="setupForm.processing" /> Registrar
                    preparación
                </Button>
            </div>
        </form>

        <!-- Lectura -->
        <form
            v-else-if="props.test.status === 'set_up'"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="read"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-medium">
                    Lectura · {{ props.test.panel
                    }}{{ props.test.is_vitek ? ' (VITEK)' : '' }}
                    <template v-if="alreadyRead.length">
                        · antibióticos agregados</template
                    >
                </h2>
                <span
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                    >Preparado {{ formatDateTime(props.test.set_up_at) }}
                    <Button
                        v-if="props.can.setup && !alreadyRead.length"
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-test="cancel-ast"
                        @click="cancelling = !cancelling"
                        >Descartar</Button
                    ></span
                >
            </div>
            <div
                v-if="cancelling"
                class="flex flex-wrap gap-2 rounded-md border p-3"
            >
                <Input
                    v-model="cancelForm.cancellation_reason"
                    class="flex-1"
                    placeholder="Motivo (p. ej., la identificación presuntiva no se confirmó)"
                    aria-label="Motivo del descarte"
                />
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="cancelForm.processing"
                    @click="cancelTest"
                    >Descartar antibiograma</Button
                >
                <InputError
                    class="w-full"
                    :message="cancelForm.errors.cancellation_reason"
                />
            </div>
            <EarlyTimeWarning
                v-if="!readable"
                v-model:confirmed="readForm.early_confirmed"
                v-model:reason="readForm.early_reason"
                :available-at="props.test.available_at"
                :error="readForm.errors.early_confirmed"
            />
            <InputError
                :message="(readForm.errors as Record<string, string>).results"
            />
            <details v-if="alreadyRead.length" class="text-sm">
                <summary class="cursor-pointer text-muted-foreground">
                    Ya leídos ({{ alreadyRead.length }})
                </summary>
                <table class="mt-2 w-full max-w-xl">
                    <tbody>
                        <tr
                            v-for="result in alreadyRead"
                            :key="result.antibiotic_id"
                            class="border-t"
                        >
                            <td class="py-1">{{ result.antibiotic }}</td>
                            <td class="py-1 text-right tabular-nums">
                                {{ result.value ?? '—' }}
                                <span class="text-xs text-muted-foreground">{{
                                    result.unit
                                }}</span>
                            </td>
                            <td class="py-1 text-center">
                                <InterpretationBadge :value="result.final" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </details>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-2 font-medium">Antibiótico</th>
                            <th class="py-2 font-medium">Valor</th>
                            <th class="py-2 text-center font-medium">
                                Automática
                            </th>
                            <th class="py-2 font-medium">
                                Interpretación revisada
                            </th>
                            <th class="py-2 text-center font-medium">
                                Informar
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in readForm.results"
                            :key="row.antibiotic_id"
                            class="border-t align-top"
                            data-test="ast-row"
                        >
                            <td class="py-2 pr-2 font-medium">
                                {{ antibioticName(row.antibiotic_id) }}
                                <span
                                    v-if="isAdditional(row.antibiotic_id)"
                                    class="block text-xs font-normal text-muted-foreground"
                                    >Fuera del panel</span
                                >
                                <span
                                    v-for="(rule, k) in rulesFor(
                                        row.antibiotic_id,
                                    )"
                                    :key="k"
                                    class="block text-xs font-normal text-amber-700 dark:text-amber-300"
                                    data-test="ast-rule"
                                    >{{ rule.note }}</span
                                >
                            </td>
                            <td class="py-2 pr-2">
                                <div class="flex gap-1">
                                    <select
                                        v-if="isMic(row)"
                                        v-model="row.mic_operator"
                                        class="h-9 rounded-md border bg-background px-1 text-sm"
                                        :aria-label="`Operador ${antibioticName(row.antibiotic_id)}`"
                                    >
                                        <option value="=">=</option>
                                        <option value="<=">≤</option>
                                        <option value=">">&gt;</option>
                                    </select>
                                    <input
                                        v-model.number="row.value"
                                        type="number"
                                        step="any"
                                        min="0"
                                        class="h-9 w-24 rounded-md border bg-background px-2 text-sm tabular-nums"
                                        :aria-label="`Valor ${antibioticName(row.antibiotic_id)}`"
                                    />
                                    <span
                                        class="self-center text-xs text-muted-foreground"
                                        >{{ unitOf(row.method) }}</span
                                    >
                                </div>
                                <InputError
                                    :message="errorFor(index, 'value')"
                                />
                            </td>
                            <td class="py-2 text-center">
                                <InterpretationBadge :value="preview(row)" />
                            </td>
                            <td class="py-2 pr-2">
                                <select
                                    v-model="row.reviewed"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                    :disabled="
                                        preview(row) !== null &&
                                        !props.can.override
                                    "
                                    :aria-label="`Interpretación revisada ${antibioticName(row.antibiotic_id)}`"
                                >
                                    <option :value="null">
                                        {{
                                            preview(row)
                                                ? 'Igual a la automática'
                                                : 'Elegir…'
                                        }}
                                    </option>
                                    <option value="S">S · Sensible</option>
                                    <option value="I">I · Intermedio</option>
                                    <option value="R">R · Resistente</option>
                                </select>
                                <Input
                                    v-if="needsReason(row)"
                                    v-model="row.override_reason"
                                    class="mt-1"
                                    placeholder="Motivo del cambio"
                                />
                                <InputError
                                    :message="
                                        errorFor(index, 'reviewed') ||
                                        errorFor(index, 'override_reason')
                                    "
                                />
                            </td>
                            <td class="py-2 text-center">
                                <input
                                    v-model="row.is_reported"
                                    type="checkbox"
                                    class="size-4"
                                    :aria-label="`Informar ${antibioticName(row.antibiotic_id)}`"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <AdditionalAntibiotics
                v-model="readForm.additional"
                :antibiotics="props.antibiotics"
                :methods="props.methods"
                :exclude="props.test.results.map((r) => r.antibiotic_id)"
                hint="Si al ver estos resultados hace falta probar otros antibióticos: lo leído queda registrado y los agregados se leen después, con la incubación del protocolo. Mientras tanto se puede emitir un informe preliminar."
                :error="
                    additionalError(readForm.errors as Record<string, string>)
                "
            />

            <div class="flex justify-end">
                <Button
                    type="submit"
                    size="lg"
                    :disabled="
                        !props.can.read ||
                        readForm.processing ||
                        (!readable && !readForm.early_confirmed)
                    "
                    data-test="save-ast"
                >
                    <Spinner v-if="readForm.processing" />
                    {{
                        readForm.additional.length
                            ? 'Registrar lectura y preparar los agregados'
                            : 'Registrar lectura del antibiograma'
                    }}
                </Button>
            </div>
        </form>

        <!-- Leído -->
        <div v-else class="grid gap-2 rounded-lg border p-4">
            <h2 class="font-medium">
                Antibiograma leído · {{ props.test.panel
                }}{{ props.test.is_vitek ? ' (VITEK)' : '' }}
            </h2>
            <table class="w-full max-w-xl text-sm">
                <tbody>
                    <tr
                        v-for="result in props.test.results"
                        :key="result.antibiotic_id"
                        class="border-t"
                    >
                        <td class="py-1">
                            {{ result.antibiotic }}
                            <span
                                v-if="result.is_additional"
                                class="text-xs text-muted-foreground"
                                >· fuera del panel</span
                            >
                        </td>
                        <td class="py-1 text-right tabular-nums">
                            {{ result.value ?? '—' }}
                            <span class="text-xs text-muted-foreground">{{
                                result.unit
                            }}</span>
                        </td>
                        <td class="py-1 text-center">
                            <InterpretationBadge :value="result.final" />
                        </td>
                        <td class="py-1 text-xs text-muted-foreground">
                            {{
                                result.override_reason ??
                                result.addition_reason ??
                                ''
                            }}
                            <span v-if="result.reported_as" class="block"
                                >Se informa como {{ result.reported_as }}</span
                            >
                            <span v-if="result.rule_notes" class="block">{{
                                result.rule_notes
                            }}</span>
                            <span v-if="!result.is_reported" class="block"
                                >No se informa</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pruebas de mecanismo y complementarias (B5) -->
        <section
            v-if="props.test || props.isolateTests.length"
            class="grid max-w-3xl gap-3 rounded-lg border p-4"
            data-test="mechanism-section"
        >
            <h2 class="flex items-center gap-2 font-medium">
                <FlaskConical class="size-4" /> Pruebas de mecanismo y
                complementarias
            </h2>
            <p class="text-xs text-muted-foreground">
                Sinergia BLEE, carbapenemasa, test D, β-lactamasa… Las marcadas
                para informar salen en el informe; las reglas del laboratorio
                pueden usar su resultado al leer el antibiograma.
            </p>
            <div
                v-for="t in props.isolateTests"
                :key="t.id"
                class="flex flex-wrap items-center gap-2 text-sm"
            >
                <span class="font-medium">{{ t.name }}:</span>
                <template v-if="t.read_at">{{ t.result }}</template>
                <template v-else>
                    <span class="text-muted-foreground"
                        >sembrada, se lee desde
                        {{ formatDateTime(t.readable_at) }}</span
                    >
                    <Link
                        :href="identifyRoute(props.isolate.ulid).url"
                        class="underline"
                        >leer</Link
                    >
                </template>
            </div>
            <template
                v-if="props.can.tests && props.isolate.status !== 'resolved'"
            >
                <div
                    v-for="(row, i) in mechanismForm.tests"
                    :key="i"
                    class="grid gap-2 sm:grid-cols-[1fr_14rem]"
                >
                    <select
                        v-model="row.test_id"
                        class="h-9 rounded-md border bg-background px-2 text-sm"
                        aria-label="Prueba"
                        @change="row.result_code = ''"
                    >
                        <option :value="null" disabled>Elegí la prueba…</option>
                        <option
                            v-for="t in props.mechanismTests"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.name }}
                        </option>
                    </select>
                    <select
                        v-model="row.result_code"
                        class="h-9 rounded-md border bg-background px-2 text-sm"
                        aria-label="Resultado"
                    >
                        <option
                            v-if="
                                mechanismOptions(row.test_id)?.incubation_hours
                            "
                            value=""
                        >
                            Sembrar (leer después)
                        </option>
                        <option v-else value="" disabled>Resultado…</option>
                        <option
                            v-for="o in mechanismOptions(row.test_id)
                                ?.options ?? []"
                            :key="o.code"
                            :value="o.code"
                        >
                            {{ o.label }}
                        </option>
                    </select>
                </div>
                <InputError
                    :message="
                        (mechanismForm.errors as Record<string, string>)[
                            'tests.0.result_code'
                        ] ?? mechanismForm.errors.tests
                    "
                />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="add-mechanism"
                        @click="
                            mechanismForm.tests.push({
                                test_id: null,
                                result_code: '',
                            })
                        "
                        >Agregar prueba</Button
                    >
                    <Button
                        v-if="mechanismForm.tests.length"
                        type="button"
                        size="sm"
                        :disabled="mechanismForm.processing"
                        @click="saveMechanism"
                        >Registrar</Button
                    >
                </div>
            </template>
        </section>
    </div>
</template>
