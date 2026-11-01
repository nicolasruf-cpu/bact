<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pill } from '@lucide/vue';
import { computed } from 'vue';
import InterpretationBadge from '@/components/domain/InterpretationBadge.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import {
    read as readRoute,
    setup as setupRoute,
} from '@/routes/susceptibility';
import type { SampleHeader } from '@/types/clinical';

type Result = {
    antibiotic_id: number;
    antibiotic: string;
    code: string;
    mic_operator: string | null;
    value: number | null;
    automatic: string | null;
    reviewed: string | null;
    final: string | null;
    override_reason: string | null;
    is_reported: boolean;
};

const props = defineProps<{
    sample: SampleHeader;
    isolate: {
        ulid: string;
        number: number;
        organism: string | null;
        group: string | null;
        count_category: string | null;
    };
    panels: {
        id: number;
        code: string;
        name: string;
        is_reference_template: boolean;
    }[];
    methods: { value: string; label: string; unit: string }[];
    test: {
        ulid: string;
        status: string;
        method: string;
        unit: string;
        panel: string;
        set_up_at: string;
        available_at: string;
        results: Result[];
        breakpoints: Record<number, { s: number; r: number }>;
    } | null;
    breakpointSet: {
        name: string;
        edition: string;
        is_synthetic: boolean;
    } | null;
    can: { setup: boolean; read: boolean; override: boolean };
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
    method: 'disk',
    panel_id: props.panels[0]?.id ?? null,
});
function setUp() {
    setupForm.post(setupRoute(props.isolate.ulid).url);
}

// --- Lectura ---------------------------------------------------------------
type Row = {
    antibiotic_id: number;
    mic_operator: string;
    value: number | null;
    reviewed: string | null;
    override_reason: string;
    is_reported: boolean;
};
const readForm = useForm({
    results: (props.test?.results ?? []).map<Row>((r) => ({
        antibiotic_id: r.antibiotic_id,
        mic_operator: r.mic_operator ?? '=',
        value: r.value,
        reviewed: null,
        override_reason: '',
        is_reported: r.is_reported,
    })),
});

const isMic = computed(() => props.test?.method === 'mic');
const readable = computed(
    () =>
        props.test !== null && new Date(props.test.available_at) <= new Date(),
);

/**
 * Vista previa de la interpretación automática, con la misma regla que el servidor
 * (BreakpointInterpreter). Es solo orientativa: el servidor recalcula y es la fuente de verdad.
 */
function preview(row: Row): string | null {
    const bp = props.test?.breakpoints[row.antibiotic_id];
    if (
        !bp ||
        row.value === null ||
        row.value === undefined ||
        Number.isNaN(Number(row.value))
    ) {
        return null;
    }
    const v = Number(row.value);
    if (isMic.value) {
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

function errorFor(index: number, field: string): string | undefined {
    return (readForm.errors as Record<string, string>)[
        `results.${index}.${field}`
    ];
}

function read() {
    readForm
        .transform((data) => ({
            results: data.results.map((r) => ({
                ...r,
                mic_operator: isMic.value ? r.mic_operator : null,
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
                <em>{{ props.isolate.organism }}</em>
            </h1>
            <span class="text-sm text-muted-foreground">{{
                props.isolate.group
            }}</span>
        </div>

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
                <Label for="method">Método</Label>
                <select
                    id="method"
                    v-model="setupForm.method"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option
                        v-for="method in props.methods"
                        :key="method.value"
                        :value="method.value"
                    >
                        {{ method.label }}
                    </option>
                </select>
            </div>
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
                        {{ panel.name
                        }}{{
                            panel.is_reference_template
                                ? ' (plantilla de referencia)'
                                : ''
                        }}
                    </option>
                </select>
                <InputError
                    :message="
                        setupForm.errors.panel_id || setupForm.errors.method
                    "
                />
            </div>
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
                    Lectura · {{ props.test.panel }} ·
                    {{ isMic ? 'CIM' : 'Difusión' }} ({{ props.test.unit }})
                </h2>
                <span class="text-sm text-muted-foreground"
                    >Preparado {{ formatDateTime(props.test.set_up_at) }}</span
                >
            </div>
            <p
                v-if="!readable"
                class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                Disponible para leer desde
                {{ formatDateTime(props.test.available_at) }}.
            </p>
            <InputError
                :message="(readForm.errors as Record<string, string>).results"
            />

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-2 font-medium">Antibiótico</th>
                            <th class="py-2 font-medium">
                                Valor ({{ props.test.unit }})
                            </th>
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
                            </td>
                            <td class="py-2 pr-2">
                                <div class="flex gap-1">
                                    <select
                                        v-if="isMic"
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

            <div class="flex justify-end">
                <Button
                    type="submit"
                    size="lg"
                    :disabled="
                        !props.can.read || readForm.processing || !readable
                    "
                    data-test="save-ast"
                >
                    <Spinner v-if="readForm.processing" /> Registrar lectura del
                    antibiograma
                </Button>
            </div>
        </form>

        <!-- Leído -->
        <div v-else class="grid gap-2 rounded-lg border p-4">
            <h2 class="font-medium">
                Antibiograma leído · {{ props.test.panel }}
            </h2>
            <table class="w-full max-w-xl text-sm">
                <tbody>
                    <tr
                        v-for="result in props.test.results"
                        :key="result.antibiotic_id"
                        class="border-t"
                    >
                        <td class="py-1">{{ result.antibiotic }}</td>
                        <td class="py-1 text-right tabular-nums">
                            {{ result.value ?? '—' }}
                        </td>
                        <td class="py-1 text-center">
                            <InterpretationBadge :value="result.final" />
                        </td>
                        <td class="py-1 text-xs text-muted-foreground">
                            {{ result.override_reason ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
