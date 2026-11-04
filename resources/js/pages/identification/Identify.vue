<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Cpu,
    Dna,
    FlaskConical,
    Plus,
    Repeat,
    Route,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import DateTimeInput from '@/components/DateTimeInput.vue';
import EarlyTimeWarning from '@/components/domain/EarlyTimeWarning.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { store as storeIdentification } from '@/routes/identifications';
import { store as storeRun } from '@/routes/instrument-runs';
import {
    read as readSubcultureRoute,
    store as storeSubculture,
} from '@/routes/isolate-subcultures';
import {
    read as readTestsRoute,
    store as storeTests,
} from '@/routes/isolate-tests';
import { significance as significanceRoute } from '@/routes/isolates';
import { show as marchRoute } from '@/routes/marches';
import {
    result as referralResultRoute,
    store as storeReferral,
} from '@/routes/referrals';
import { show as susceptibilityRoute } from '@/routes/susceptibility';
import type { SampleHeader } from '@/types/clinical';

type Organism = {
    id: number;
    name: string;
    group: string;
    group_label: string;
    has_panel: boolean;
};
type CodeLabel = { code: string; label: string };
type ValueLabel = { value: string; label: string };
type CatalogTest = {
    id: number;
    code: string;
    name: string;
    kind: string;
    kind_label: string;
    options: CodeLabel[];
    incubation_hours: number | null;
};
type IsolateTest = {
    id: number;
    code: string;
    name: string;
    kind: string;
    kind_label: string;
    options: CodeLabel[];
    set_up_at: string;
    readable_at: string;
    read_at: string | null;
    result_code: string | null;
    result: string | null;
    notes: string | null;
};

const props = defineProps<{
    sample: SampleHeader;
    isolate: {
        ulid: string;
        number: number;
        morphotype: string;
        count_category: string | null;
        plate: string | null;
        status: string;
        status_label: string;
        organism: string | null;
        significance: string;
        significance_label: string;
        significance_reason: string | null;
        culture_closed: boolean;
        study_id: number;
    };
    history: {
        organism: string | null;
        method: string;
        level: string;
        level_value: string;
        tests: string | null;
        confidence: string | null;
        notes: string | null;
        card: string | null;
        identified_at: string;
    }[];
    tests: IsolateTest[];
    catalog: CatalogTest[];
    runs: {
        id: number;
        instrument: string;
        id_card: string | null;
        ast_card: string | null;
        cassette: string | null;
        position: string | null;
        suspension: string | null;
        loaded_at: string;
        id_result_at: string | null;
        ast_result_at: string | null;
        pending: boolean;
    }[];
    subcultures: {
        id: number;
        medium: string;
        atmosphere: string;
        reason: string | null;
        subcultured_at: string;
        readable_at: string;
        result: string | null;
        notes: string | null;
        read_at: string | null;
    }[];
    referrals: {
        id: number;
        destination: string;
        reason: string;
        sent_at: string;
        result: string | null;
        result_at: string | null;
    }[];
    susceptibility: { status: string; panel: string; set_up_at: string } | null;
    allowPresumptive: boolean;
    vitekPanels: { id: number; name: string }[];
    organisms: Organism[];
    methods: ValueLabel[];
    media: { id: number; name: string }[];
    atmospheres: ValueLabel[];
    significances: ValueLabel[];
    subcultureResults: ValueLabel[];
    can: {
        identify: boolean;
        setup_tests: boolean;
        read_tests: boolean;
        vitek: boolean;
        susceptibility: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mesada de identificación',
                href: '/workbenches/identification',
            },
            { title: 'Identificación', href: '#' },
        ],
    },
});

const inIdentification = computed(
    () => props.isolate.status === 'in_identification',
);
const open = computed(() => !props.isolate.culture_closed);

// ---- Identificación ----
const form = useForm({
    organism_id: null as number | null,
    method: props.methods[0]?.value ?? 'biochemical',
    level: 'definitive',
    tests: '',
    confidence: '',
    notes: '',
    requires_susceptibility: props.isolate.significance === 'significant',
    no_ast_reason: '',
});

const organism = computed(() =>
    props.organisms.find((o) => o.id === form.organism_id),
);

// Si el grupo no tiene panel, no se puede pedir antibiograma.
watch(organism, (value) => {
    if (value && !value.has_panel && !props.susceptibility) {
        form.requires_susceptibility = false;
    }
});

const inconclusive = ref(false);

function submit(level: 'preliminary' | 'definitive' | 'inconclusive') {
    form.level = level;
    form.post(storeIdentification(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            inconclusive.value = false;
        },
    });
}

// ---- Gram y pruebas (B1, B2) ----
const catalogById = computed(
    () => new Map(props.catalog.map((t) => [t.id, t])),
);
const testsForm = useForm({
    tests: [] as {
        test_id: number | null;
        result_code: string;
        notes: string;
    }[],
});
function addTest(testId: number | null = null) {
    testsForm.tests.push({ test_id: testId, result_code: '', notes: '' });
}
const quickTests = computed(() =>
    props.catalog.filter((t) => ['GRAM', 'CAT', 'OXI'].includes(t.code)),
);
function saveTests() {
    testsForm
        .transform((data) => ({
            tests: data.tests.filter((t) => t.test_id !== null),
        }))
        .post(storeTests(props.isolate.ulid).url, {
            preserveScroll: true,
            onSuccess: () => testsForm.reset(),
        });
}
function testError(index: number, field: string): string | undefined {
    return (testsForm.errors as Record<string, string>)[
        `tests.${index}.${field}`
    ];
}

const pendingTests = computed(() => props.tests.filter((t) => !t.read_at));
const readTests = computed(() => props.tests.filter((t) => t.read_at));
const pendingResults = reactive<Record<number, string>>({});
const readForm = useForm({
    results: [] as { id: number; result_code: string }[],
    early_confirmed: false,
    early_reason: '',
});
const latestReadable = computed(() => {
    const chosen = pendingTests.value.filter((t) => pendingResults[t.id]);
    if (!chosen.length) {
        return null;
    }

    return chosen
        .map((t) => t.readable_at)
        .sort()
        .at(-1) as string;
});
const readEarly = computed(
    () =>
        latestReadable.value !== null &&
        new Date(latestReadable.value) > new Date(),
);
function saveReadings() {
    readForm
        .transform((data) => ({
            ...data,
            results: pendingTests.value
                .filter((t) => pendingResults[t.id])
                .map((t) => ({ id: t.id, result_code: pendingResults[t.id] })),
        }))
        .post(readTestsRoute(props.isolate.ulid).url, { preserveScroll: true });
}

// ---- VITEK (B3) ----
const showRun = ref(false);
const runForm = useForm({
    instrument: 'VITEK 2',
    id_card_type: '',
    id_card_number: '',
    ast_card_type: '',
    ast_card_number: '',
    ast_panel_id: null as number | null,
    cassette: '',
    position: '',
    suspension: '',
    loaded_at: null as string | null,
});
function saveRun() {
    runForm.post(storeRun(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            runForm.reset();
            showRun.value = false;
        },
    });
}

// ---- Repique (B4) ----
const showSubculture = ref(false);
const subcultureForm = useForm({
    medium_id: null as number | null,
    incubation_temperature: '35 ± 2 °C',
    atmosphere: 'aerobic',
    reason: '',
});
function saveSubculture() {
    subcultureForm.post(storeSubculture(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            subcultureForm.reset();
            showSubculture.value = false;
        },
    });
}
const subcultureRead = reactive<
    Record<number, { result: string; notes: string }>
>({});
const subcultureReadForm = useForm({ result: '', notes: '' });
function readSubculture(id: number) {
    const row = subcultureRead[id];
    subcultureReadForm
        .transform(() => ({
            result: row?.result ?? '',
            notes: row?.notes ?? '',
        }))
        .post(readSubcultureRoute(id).url, { preserveScroll: true });
}

// ---- Derivación (B9) ----
const showReferral = ref(false);
const referralForm = useForm({
    destination: '',
    reason: '',
    sent_at: null as string | null,
});
function saveReferral() {
    referralForm.post(storeReferral(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            referralForm.reset();
            showReferral.value = false;
        },
    });
}
const referralResults = reactive<Record<number, string>>({});
const referralResultForm = useForm({ result: '' });
function saveReferralResult(id: number) {
    referralResultForm
        .transform(() => ({ result: referralResults[id] ?? '' }))
        .post(referralResultRoute(id).url, { preserveScroll: true });
}

// ---- Significancia (A4) ----
const showSignificance = ref(false);
const significanceForm = useForm({
    significance: props.isolate.significance,
    significance_reason: '',
});
function saveSignificance() {
    significanceForm.post(significanceRoute(props.isolate.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            showSignificance.value = false;
            significanceForm.reset('significance_reason');
        },
    });
}

const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="`Identificación ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid content-start gap-4">
                <!-- Encabezado del aislamiento -->
                <section class="grid gap-2 rounded-lg border p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <h1
                                class="flex items-center gap-2 text-xl font-semibold"
                            >
                                <Dna class="size-5" /> Aislamiento
                                {{ props.isolate.number }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{ props.isolate.morphotype }}
                                <template v-if="props.isolate.plate">
                                    · {{ props.isolate.plate }}</template
                                >
                                · {{ props.isolate.status_label }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                class="rounded-full border px-2 py-0.5"
                                :class="
                                    props.isolate.significance === 'significant'
                                        ? ''
                                        : 'border-amber-500/60 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                "
                                data-test="significance-badge"
                                >{{ props.isolate.significance_label }}</span
                            >
                            <Button
                                v-if="open"
                                type="button"
                                variant="outline"
                                size="sm"
                                data-test="change-significance"
                                @click="showSignificance = !showSignificance"
                                >Cambiar</Button
                            >
                            <Link
                                :href="marchRoute(props.isolate.study_id).url"
                                class="flex items-center gap-1 underline"
                                ><Route class="size-4" /> Marcha</Link
                            >
                        </div>
                    </div>
                    <p
                        v-if="props.isolate.significance_reason"
                        class="text-xs text-muted-foreground"
                    >
                        Motivo: {{ props.isolate.significance_reason }}
                    </p>
                    <div
                        v-if="showSignificance"
                        class="grid gap-2 rounded-md border p-3 sm:grid-cols-[12rem_1fr_auto]"
                    >
                        <select
                            v-model="significanceForm.significance"
                            :class="selectClass"
                            aria-label="Significancia"
                        >
                            <option
                                v-for="s in props.significances"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                        <Input
                            v-model="significanceForm.significance_reason"
                            placeholder="Motivo (p. ej., 1 de 2 hemocultivos)"
                            aria-label="Motivo del cambio"
                        />
                        <Button
                            type="button"
                            :disabled="significanceForm.processing"
                            @click="saveSignificance"
                            >Guardar</Button
                        >
                        <InputError
                            class="sm:col-span-3"
                            :message="
                                significanceForm.errors.significance_reason ??
                                significanceForm.errors.significance
                            "
                        />
                    </div>
                    <p
                        v-if="props.susceptibility"
                        class="text-sm"
                        data-test="susceptibility-link"
                    >
                        Antibiograma: {{ props.susceptibility.panel }} ·
                        {{ props.susceptibility.status }} ·
                        <Link
                            :href="susceptibilityRoute(props.isolate.ulid).url"
                            class="underline"
                            >ver</Link
                        >
                    </p>
                    <p
                        v-else-if="
                            inIdentification &&
                            props.allowPresumptive &&
                            props.can.susceptibility &&
                            props.isolate.significance === 'significant'
                        "
                        class="text-sm"
                    >
                        El protocolo permite preparar el antibiograma con la
                        identificación presuntiva.
                        <Link
                            :href="susceptibilityRoute(props.isolate.ulid).url"
                            class="underline"
                            data-test="presumptive-ast"
                            >Preparar antibiograma</Link
                        >
                    </p>
                </section>

                <!-- Gram y pruebas -->
                <section
                    class="grid gap-3 rounded-lg border p-4"
                    data-test="tests-section"
                >
                    <h2 class="flex items-center gap-2 font-medium">
                        <FlaskConical class="size-4" /> Gram, pruebas rápidas y
                        bioquímicas
                    </h2>
                    <table v-if="props.tests.length" class="w-full text-sm">
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Prueba</th>
                                <th class="py-1 font-medium">Resultado</th>
                                <th class="py-1 font-medium">Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="test in readTests"
                                :key="test.id"
                                class="border-t"
                            >
                                <td class="py-1">
                                    {{ test.name }}
                                    <span class="text-xs text-muted-foreground"
                                        >· {{ test.kind_label }}</span
                                    >
                                </td>
                                <td class="py-1 font-medium">
                                    {{ test.result }}
                                    <span
                                        v-if="test.notes"
                                        class="font-normal text-muted-foreground"
                                        >({{ test.notes }})</span
                                    >
                                </td>
                                <td class="py-1 text-xs text-muted-foreground">
                                    {{ formatDateTime(test.read_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="pendingTests.length"
                        class="grid gap-2 rounded-md border bg-muted/30 p-3"
                        data-test="pending-tests"
                    >
                        <h3 class="text-sm font-medium">
                            Sembradas, para leer
                        </h3>
                        <div
                            v-for="test in pendingTests"
                            :key="test.id"
                            class="grid gap-2 sm:grid-cols-[1fr_14rem]"
                        >
                            <span class="text-sm"
                                >{{ test.name }}
                                <span class="text-xs text-muted-foreground"
                                    >· sembrada
                                    {{ formatDateTime(test.set_up_at) }} · se
                                    lee desde
                                    {{ formatDateTime(test.readable_at) }}</span
                                ></span
                            >
                            <select
                                v-model="pendingResults[test.id]"
                                :class="selectClass"
                                :aria-label="`Resultado de ${test.name}`"
                                :disabled="!props.can.read_tests"
                            >
                                <option :value="undefined">Sin leer</option>
                                <option
                                    v-for="o in test.options"
                                    :key="o.code"
                                    :value="o.code"
                                >
                                    {{ o.label }}
                                </option>
                            </select>
                        </div>
                        <EarlyTimeWarning
                            v-if="readEarly && latestReadable"
                            v-model:confirmed="readForm.early_confirmed"
                            v-model:reason="readForm.early_reason"
                            :available-at="latestReadable"
                            :error="readForm.errors.early_confirmed"
                        />
                        <InputError :message="readForm.errors.results" />
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                size="sm"
                                :disabled="
                                    readForm.processing ||
                                    latestReadable === null ||
                                    (readEarly && !readForm.early_confirmed)
                                "
                                data-test="read-tests"
                                @click="saveReadings"
                                >Registrar lectura de pruebas</Button
                            >
                        </div>
                    </div>

                    <template v-if="open && props.can.setup_tests">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="t in quickTests"
                                :key="t.id"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addTest(t.id)"
                                ><Plus class="size-4" /> {{ t.name }}</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                data-test="add-test"
                                @click="addTest()"
                                ><Plus class="size-4" /> Otra prueba</Button
                            >
                        </div>
                        <div
                            v-for="(row, index) in testsForm.tests"
                            :key="index"
                            class="grid gap-2 sm:grid-cols-[1fr_14rem_auto]"
                        >
                            <select
                                v-model="row.test_id"
                                :class="selectClass"
                                aria-label="Prueba"
                                @change="row.result_code = ''"
                            >
                                <option :value="null" disabled>
                                    Elegí la prueba…
                                </option>
                                <optgroup
                                    v-for="kind in [
                                        ...new Set(
                                            props.catalog.map(
                                                (t) => t.kind_label,
                                            ),
                                        ),
                                    ]"
                                    :key="kind"
                                    :label="kind"
                                >
                                    <option
                                        v-for="t in props.catalog.filter(
                                            (c) => c.kind_label === kind,
                                        )"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        {{ t.name }}
                                    </option>
                                </optgroup>
                            </select>
                            <select
                                v-model="row.result_code"
                                :class="selectClass"
                                aria-label="Resultado"
                                :disabled="row.test_id === null"
                            >
                                <option
                                    v-if="
                                        row.test_id !== null &&
                                        catalogById.get(row.test_id)
                                            ?.incubation_hours
                                    "
                                    value=""
                                >
                                    Sembrar (leer a las
                                    {{
                                        catalogById.get(row.test_id)
                                            ?.incubation_hours
                                    }}
                                    h)
                                </option>
                                <option v-else value="" disabled>
                                    Resultado…
                                </option>
                                <option
                                    v-for="o in row.test_id === null
                                        ? []
                                        : (catalogById.get(row.test_id)
                                              ?.options ?? [])"
                                    :key="o.code"
                                    :value="o.code"
                                >
                                    {{ o.label }}
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar prueba"
                                @click="testsForm.tests.splice(index, 1)"
                                ><Trash2 class="size-4"
                            /></Button>
                            <InputError
                                class="sm:col-span-3"
                                :message="
                                    testError(index, 'result_code') ??
                                    testError(index, 'test_id')
                                "
                            />
                        </div>
                        <InputError :message="testsForm.errors.tests" />
                        <div
                            v-if="testsForm.tests.length"
                            class="flex justify-end"
                        >
                            <Button
                                type="button"
                                size="sm"
                                :disabled="testsForm.processing"
                                data-test="save-tests"
                                @click="saveTests"
                                >Registrar pruebas</Button
                            >
                        </div>
                    </template>
                </section>

                <!-- Equipo (VITEK) -->
                <section
                    class="grid gap-3 rounded-lg border p-4"
                    data-test="vitek-section"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-medium">
                            <Cpu class="size-4" /> Equipo
                        </h2>
                        <Button
                            v-if="
                                open &&
                                props.can.vitek &&
                                !props.isolate.culture_closed
                            "
                            type="button"
                            variant="outline"
                            size="sm"
                            data-test="load-vitek"
                            @click="showRun = !showRun"
                            >Cargar tarjetas</Button
                        >
                    </div>
                    <div
                        v-for="run in props.runs"
                        :key="run.id"
                        class="rounded-md border p-2 text-sm"
                    >
                        <div class="font-medium">
                            {{ run.instrument }} ·
                            {{ formatDateTime(run.loaded_at) }}
                            <span
                                v-if="run.pending"
                                class="font-normal text-amber-700 dark:text-amber-300"
                            >
                                · resultado pendiente</span
                            >
                        </div>
                        <div class="text-xs text-muted-foreground">
                            <template v-if="run.id_card"
                                >ID: {{ run.id_card }}
                                <template v-if="run.id_result_at"
                                    >(resultado
                                    {{
                                        formatDateTime(run.id_result_at)
                                    }})</template
                                >
                                ·
                            </template>
                            <template v-if="run.ast_card"
                                >ATB: {{ run.ast_card }}
                                <template v-if="run.ast_result_at"
                                    >(resultado
                                    {{
                                        formatDateTime(run.ast_result_at)
                                    }})</template
                                >
                                ·
                            </template>
                            <template v-if="run.cassette"
                                >casete {{ run.cassette }}
                                <template v-if="run.position"
                                    >/ {{ run.position }}</template
                                ></template
                            >
                            <template v-if="run.suspension">
                                · suspensión {{ run.suspension }}</template
                            >
                        </div>
                    </div>
                    <div
                        v-if="showRun"
                        class="grid gap-3 rounded-md border p-3"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label for="instrument">Equipo</Label>
                                <Input
                                    id="instrument"
                                    v-model="runForm.instrument"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="loaded_at">Hora de carga</Label>
                                <DateTimeInput
                                    id="loaded_at"
                                    v-model="runForm.loaded_at"
                                />
                                <InputError
                                    :message="runForm.errors.loaded_at"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="id_card"
                                    >Tarjeta de identificación</Label
                                >
                                <div class="flex gap-2">
                                    <Input
                                        id="id_card"
                                        v-model="runForm.id_card_type"
                                        placeholder="Tipo (GN, GP, YST…)"
                                    />
                                    <Input
                                        v-model="runForm.id_card_number"
                                        placeholder="N.º"
                                        aria-label="Número de la tarjeta de identificación"
                                    />
                                </div>
                                <InputError
                                    :message="runForm.errors.id_card_type"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="ast_card"
                                    >Tarjeta de antibiograma</Label
                                >
                                <div class="flex gap-2">
                                    <Input
                                        id="ast_card"
                                        v-model="runForm.ast_card_type"
                                        placeholder="Tipo (AST-N…)"
                                    />
                                    <Input
                                        v-model="runForm.ast_card_number"
                                        placeholder="N.º"
                                        aria-label="Número de la tarjeta de antibiograma"
                                    />
                                </div>
                                <InputError
                                    :message="runForm.errors.ast_card_type"
                                />
                            </div>
                            <div
                                v-if="runForm.ast_card_type"
                                class="grid gap-1 sm:col-span-2"
                            >
                                <Label for="ast_panel"
                                    >Panel VITEK de la tarjeta</Label
                                >
                                <select
                                    id="ast_panel"
                                    v-model="runForm.ast_panel_id"
                                    :class="selectClass"
                                >
                                    <option :value="null" disabled>
                                        Elegí el panel…
                                    </option>
                                    <option
                                        v-for="p in props.vitekPanels"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    El antibiograma queda preparado con la hora
                                    de carga. Se lee con la identificación
                                    definitiva.
                                </p>
                                <InputError
                                    :message="
                                        runForm.errors.ast_panel_id ??
                                        (
                                            runForm.errors as Record<
                                                string,
                                                string
                                            >
                                        ).method
                                    "
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="cassette">Casete / posición</Label>
                                <div class="flex gap-2">
                                    <Input
                                        id="cassette"
                                        v-model="runForm.cassette"
                                        placeholder="Casete"
                                    />
                                    <Input
                                        v-model="runForm.position"
                                        placeholder="Posición"
                                        aria-label="Posición"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label for="suspension">Suspensión</Label>
                                <Input
                                    id="suspension"
                                    v-model="runForm.suspension"
                                    placeholder="p. ej., 0,55 McFarland"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="button"
                                :disabled="runForm.processing"
                                data-test="save-run"
                                @click="saveRun"
                                >Registrar carga</Button
                            >
                        </div>
                    </div>
                </section>

                <!-- Identificación -->
                <form
                    v-if="inIdentification && props.can.identify"
                    class="grid content-start gap-5 rounded-lg border p-4"
                    @submit.prevent="submit('definitive')"
                >
                    <h2 class="flex items-center gap-2 font-medium">
                        <Dna class="size-4" /> Identificación
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <Label for="method">Método</Label>
                            <select
                                id="method"
                                v-model="form.method"
                                :class="selectClass"
                            >
                                <option
                                    v-for="method in props.methods"
                                    :key="method.value"
                                    :value="method.value"
                                >
                                    {{ method.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.method" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="organism">Organismo</Label>
                            <select
                                id="organism"
                                v-model="form.organism_id"
                                :class="selectClass"
                                data-test="organism"
                            >
                                <option :value="null">
                                    {{
                                        inconclusive
                                            ? 'Sin organismo'
                                            : 'Elegí el organismo'
                                    }}
                                </option>
                                <option
                                    v-for="o in props.organisms"
                                    :key="o.id"
                                    :value="o.id"
                                >
                                    {{ o.name }} ({{ o.group_label }})
                                </option>
                            </select>
                            <InputError :message="form.errors.organism_id" />
                        </div>
                    </div>

                    <div class="grid gap-1">
                        <Label for="tests">Otras pruebas / comentario</Label>
                        <textarea
                            id="tests"
                            v-model="form.tests"
                            rows="2"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Las pruebas del catálogo se registran arriba; acá va lo que no esté en el catálogo."
                        />
                    </div>
                    <div
                        v-if="form.method === 'vitek'"
                        class="grid gap-1 sm:w-1/2"
                    >
                        <Label for="confidence">Confianza VITEK</Label>
                        <Input
                            id="confidence"
                            v-model="form.confidence"
                            placeholder="p. ej., 99 % excelente"
                        />
                    </div>

                    <fieldset class="grid gap-2 rounded-md border p-3">
                        <legend class="px-1 text-sm font-medium">
                            Antibiograma
                        </legend>
                        <p
                            v-if="
                                props.isolate.significance ===
                                'probable_contaminant'
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Probable contaminante: se identifica sin
                            antibiograma.
                        </p>
                        <label v-else class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.requires_susceptibility"
                                type="checkbox"
                                class="size-4"
                                :disabled="
                                    organism !== undefined &&
                                    !organism.has_panel &&
                                    !props.susceptibility
                                "
                            />
                            Requiere antibiograma
                        </label>
                        <p
                            v-if="
                                organism &&
                                !organism.has_panel &&
                                !props.susceptibility
                            "
                            class="text-xs text-muted-foreground"
                        >
                            No hay panel configurado para
                            {{ organism.group_label }} en esta muestra.
                        </p>
                        <p
                            v-if="
                                props.susceptibility &&
                                !form.requires_susceptibility
                            "
                            class="text-xs text-amber-700 dark:text-amber-300"
                        >
                            El antibiograma preparado con la presuntiva se
                            descarta.
                        </p>
                        <InputError
                            :message="form.errors.requires_susceptibility"
                        />
                        <div
                            v-if="!form.requires_susceptibility"
                            class="grid gap-1"
                        >
                            <Label for="no_ast_reason"
                                >Motivo por el que no requiere
                                antibiograma</Label
                            >
                            <Input
                                id="no_ast_reason"
                                v-model="form.no_ast_reason"
                            />
                            <InputError :message="form.errors.no_ast_reason" />
                        </div>
                    </fieldset>

                    <div v-if="inconclusive" class="grid gap-1">
                        <Label for="notes">Por qué no es concluyente</Label>
                        <Input
                            id="notes"
                            v-model="form.notes"
                            placeholder="p. ej., perfil bajo, mezcla de colonias"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            v-if="!inconclusive"
                            type="button"
                            variant="ghost"
                            data-test="inconclusive"
                            @click="inconclusive = true"
                            >No concluyente…</Button
                        >
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            data-test="save-inconclusive"
                            @click="submit('inconclusive')"
                            >Registrar no concluyente</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing || !form.organism_id"
                            @click="submit('preliminary')"
                        >
                            Registrar preliminar
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="form.processing || !form.organism_id"
                            data-test="save-identification"
                        >
                            <Spinner v-if="form.processing" /> Confirmar
                            identificación definitiva
                        </Button>
                    </div>
                </form>

                <!-- Repique -->
                <section
                    class="grid gap-3 rounded-lg border p-4"
                    data-test="subculture-section"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-medium">
                            <Repeat class="size-4" /> Repique / purificación
                        </h2>
                        <Button
                            v-if="
                                open &&
                                props.can.setup_tests &&
                                props.isolate.status !== 'resolved'
                            "
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showSubculture = !showSubculture"
                            >Repicar</Button
                        >
                    </div>
                    <div
                        v-for="sub in props.subcultures"
                        :key="sub.id"
                        class="grid gap-2 rounded-md border p-2 text-sm"
                    >
                        <div>
                            {{ sub.medium }} · {{ sub.atmosphere }} ·
                            {{ formatDateTime(sub.subcultured_at) }}
                            <span
                                v-if="sub.reason"
                                class="text-muted-foreground"
                                >— {{ sub.reason }}</span
                            >
                        </div>
                        <div v-if="sub.read_at" class="font-medium">
                            {{ sub.result }}
                            <span
                                v-if="sub.notes"
                                class="font-normal text-muted-foreground"
                                >({{ sub.notes }})</span
                            >
                        </div>
                        <div
                            v-else-if="props.can.read_tests"
                            class="grid gap-2 sm:grid-cols-[12rem_1fr_auto]"
                        >
                            <select
                                v-model="
                                    (subcultureRead[sub.id] ??= {
                                        result: '',
                                        notes: '',
                                    }).result
                                "
                                :class="selectClass"
                                aria-label="Resultado del repique"
                            >
                                <option value="" disabled>
                                    Se lee desde
                                    {{ formatDateTime(sub.readable_at) }}
                                </option>
                                <option
                                    v-for="r in props.subcultureResults"
                                    :key="r.value"
                                    :value="r.value"
                                >
                                    {{ r.label }}
                                </option>
                            </select>
                            <Input
                                v-model="
                                    (subcultureRead[sub.id] ??= {
                                        result: '',
                                        notes: '',
                                    }).notes
                                "
                                placeholder="Observación"
                                aria-label="Observación del repique"
                            />
                            <Button
                                type="button"
                                size="sm"
                                :disabled="subcultureReadForm.processing"
                                @click="readSubculture(sub.id)"
                                >Registrar</Button
                            >
                        </div>
                    </div>
                    <div
                        v-if="showSubculture"
                        class="grid gap-2 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <select
                            v-model="subcultureForm.medium_id"
                            :class="selectClass"
                            aria-label="Medio"
                        >
                            <option :value="null" disabled>Medio…</option>
                            <option
                                v-for="m in props.media"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ m.name }}
                            </option>
                        </select>
                        <select
                            v-model="subcultureForm.atmosphere"
                            :class="selectClass"
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
                        <Input
                            v-model="subcultureForm.incubation_temperature"
                            aria-label="Temperatura"
                        />
                        <Input
                            v-model="subcultureForm.reason"
                            placeholder="Motivo (no está puro, más masa…)"
                            aria-label="Motivo del repique"
                        />
                        <InputError
                            class="sm:col-span-2"
                            :message="subcultureForm.errors.medium_id"
                        />
                        <div class="flex justify-end sm:col-span-2">
                            <Button
                                type="button"
                                size="sm"
                                :disabled="subcultureForm.processing"
                                @click="saveSubculture"
                                >Registrar repique</Button
                            >
                        </div>
                    </div>
                </section>

                <!-- Derivación -->
                <section
                    class="grid gap-3 rounded-lg border p-4"
                    data-test="referral-section"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-medium">
                            <Send class="size-4" /> Derivación
                        </h2>
                        <Button
                            v-if="props.can.identify"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showReferral = !showReferral"
                            >Derivar</Button
                        >
                    </div>
                    <div
                        v-for="referral in props.referrals"
                        :key="referral.id"
                        class="grid gap-2 rounded-md border p-2 text-sm"
                    >
                        <div>
                            {{ referral.destination }} · {{ referral.reason }} ·
                            {{ formatDateTime(referral.sent_at) }}
                        </div>
                        <div v-if="referral.result_at" class="font-medium">
                            {{ referral.result }}
                        </div>
                        <div v-else-if="props.can.identify" class="flex gap-2">
                            <Input
                                v-model="referralResults[referral.id]"
                                placeholder="Resultado informado"
                                aria-label="Resultado de la derivación"
                            />
                            <Button
                                type="button"
                                size="sm"
                                :disabled="referralResultForm.processing"
                                @click="saveReferralResult(referral.id)"
                                >Registrar</Button
                            >
                        </div>
                    </div>
                    <div
                        v-if="showReferral"
                        class="grid gap-2 rounded-md border p-3 sm:grid-cols-2"
                    >
                        <Input
                            v-model="referralForm.destination"
                            placeholder="Laboratorio de referencia"
                            aria-label="Destino"
                        />
                        <Input
                            v-model="referralForm.reason"
                            placeholder="Para qué (serogrupo, confirmación…)"
                            aria-label="Motivo"
                        />
                        <div class="grid gap-1 sm:col-span-2">
                            <Label for="sent_at">Enviado</Label>
                            <DateTimeInput
                                id="sent_at"
                                v-model="referralForm.sent_at"
                            />
                        </div>
                        <InputError
                            class="sm:col-span-2"
                            :message="
                                referralForm.errors.destination ??
                                referralForm.errors.reason
                            "
                        />
                        <div class="flex justify-end sm:col-span-2">
                            <Button
                                type="button"
                                size="sm"
                                :disabled="referralForm.processing"
                                @click="saveReferral"
                                >Registrar derivación</Button
                            >
                        </div>
                    </div>
                </section>
            </div>

            <aside
                class="grid content-start gap-2 rounded-lg border p-4 text-sm"
            >
                <h2 class="font-medium">Identificaciones registradas</h2>
                <p
                    v-if="props.history.length === 0"
                    class="text-muted-foreground"
                >
                    Ninguna todavía.
                </p>
                <div
                    v-for="(item, index) in props.history"
                    :key="index"
                    class="rounded-md border p-2"
                >
                    <div class="font-medium">
                        <em>{{ item.organism ?? 'Sin organismo' }}</em> ·
                        {{ item.level }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ item.method }}
                        <template v-if="item.card"> · {{ item.card }}</template>
                        <template v-if="item.confidence">
                            · {{ item.confidence }}</template
                        >
                        · {{ formatDateTime(item.identified_at) }}
                    </div>
                    <div v-if="item.notes" class="text-xs">
                        {{ item.notes }}
                    </div>
                    <div v-if="item.tests" class="text-xs">
                        {{ item.tests }}
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
