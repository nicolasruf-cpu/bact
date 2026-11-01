<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, Plus, Trash2, Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { statusClass } from '@/lib/configStatus';
import { formatDateTime } from '@/lib/datetime';
import {
    exportMethod as exportSet,
    importMethod as importCsv,
    index,
    save,
    status as changeStatus,
} from '@/routes/admin/breakpoint-sets';

type Row = {
    organism_group: string;
    antibiotic_code: string;
    antibiotic?: string;
    method: string;
    susceptible: string;
    resistant: string;
};

const props = defineProps<{
    set: {
        id: number;
        name: string;
        edition: string;
        notes: string | null;
        status: string;
        status_label: string;
        is_synthetic: boolean;
        lock_version: number;
        created_by: string | null;
        approved_by: string | null;
        approved_at: string | null;
        activated_at: string | null;
        retired_at: string | null;
        tests_count: number;
        breakpoints: Row[];
    };
    groups: { value: string; label: string }[];
    methods: { value: string; label: string }[];
    antibiotics: { code: string; name: string }[];
    can: { draft: boolean; approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Puntos de corte', href: index() },
            { title: 'Set', href: '#' },
        ],
    },
});

const isDraft = computed(() => props.set.status === 'draft');
const editable = computed(
    () => isDraft.value && props.can.draft && !props.set.is_synthetic,
);
const groupLabel = (value: string) =>
    props.groups.find((g) => g.value === value)?.label ?? value;
const methodLabel = (value: string) =>
    props.methods.find((m) => m.value === value)?.label ?? value;

// Importación CSV.
const upload = useForm({
    file: null as File | null,
    lock_version: props.set.lock_version,
});
function sendFile() {
    upload.post(importCsv(props.set.id).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => upload.reset('file'),
    });
}

// Carga en la tabla.
const table = useForm({
    rows: props.set.breakpoints.map(
        ({ antibiotic: _, ...row }) => row,
    ) as Row[],
    lock_version: props.set.lock_version,
});
function saveTable() {
    table.put(save(props.set.id).url, {
        preserveScroll: true,
        onSuccess: () => table.defaults(),
    });
}
watch(
    () => props.set.breakpoints,
    (rows) => {
        table.rows = rows.map(({ antibiotic: _, ...row }) => row);
        table.defaults('rows', table.rows);
    },
);

const review = useForm({
    status: '',
    lock_version: props.set.lock_version,
    reason: '',
});
watch(
    () => props.set.lock_version,
    (lock) => {
        upload.lock_version = lock;
        table.lock_version = lock;
        table.defaults('lock_version', lock);
        review.lock_version = lock;
    },
);
const retiring = ref(false);
function setStatus(status: string) {
    review.status = status;
    review.post(changeStatus(props.set.id).url, {
        preserveScroll: true,
        onSuccess: () => (retiring.value = false),
    });
}

const errors = computed(() => [
    ...Object.values(upload.errors as Record<string, string>),
    ...Object.values(table.errors as Record<string, string>),
    ...Object.values(review.errors as Record<string, string>),
]);

const filter = ref('');
const visibleRows = computed(() =>
    table.rows
        .map((row, i) => ({ row, i }))
        .filter(
            ({ row }) =>
                !filter.value ||
                `${row.organism_group} ${row.antibiotic_code}`
                    .toLowerCase()
                    .includes(filter.value.toLowerCase()),
        ),
);
const inputClass = 'h-8 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="props.set.name" />

    <div class="grid gap-4 p-4">
        <section class="grid gap-3 rounded-lg border p-4">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold">{{ props.set.name }}</h1>
                <span
                    class="rounded border px-2 py-0.5 text-xs"
                    :class="statusClass(props.set.status)"
                    data-test="set-status"
                    >{{ props.set.status_label }}</span
                >
                <Badge v-if="props.set.is_synthetic" variant="destructive"
                    >Valores ficticios · solo demo · nunca en producción</Badge
                >
            </div>
            <p class="text-sm text-muted-foreground">
                Edición: {{ props.set.edition }}
                <span v-if="props.set.created_by">
                    · Preparó: {{ props.set.created_by }}</span
                >
                <span v-if="props.set.approved_by">
                    · Aprobó: {{ props.set.approved_by }} ({{
                        formatDateTime(props.set.approved_at!)
                    }})</span
                >
                <span v-if="props.set.activated_at">
                    · Vigente desde
                    {{ formatDateTime(props.set.activated_at) }}</span
                >
                · {{ props.set.tests_count }} antibiogramas interpretados con
                este set.
            </p>
            <p v-if="props.set.notes" class="text-sm">{{ props.set.notes }}</p>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="!props.set.is_synthetic"
                    variant="outline"
                    as-child
                >
                    <a :href="exportSet(props.set.id).url"
                        ><Download class="size-4" /> Descargar CSV</a
                    >
                </Button>
                <Button
                    v-if="
                        isDraft && props.can.approve && !props.set.is_synthetic
                    "
                    :disabled="review.processing || table.isDirty"
                    data-test="approve-set"
                    @click="setStatus('approved')"
                    >Aprobar</Button
                >
                <Button
                    v-if="props.set.status === 'approved' && props.can.approve"
                    data-test="activate-set"
                    @click="setStatus('active')"
                    >Poner vigente</Button
                >
                <template
                    v-if="
                        !props.set.is_synthetic &&
                        props.set.status !== 'retired' &&
                        (props.can.approve || (isDraft && props.can.draft))
                    "
                >
                    <Button
                        v-if="!retiring"
                        variant="ghost"
                        @click="retiring = true"
                        >{{
                            isDraft ? 'Descartar borrador' : 'Retirar'
                        }}</Button
                    >
                    <template v-else>
                        <Input
                            v-model="review.reason"
                            class="max-w-sm"
                            placeholder="Motivo"
                        />
                        <Button
                            variant="destructive"
                            :disabled="!review.reason || review.processing"
                            @click="setStatus('retired')"
                            >Confirmar</Button
                        >
                        <Button variant="ghost" @click="retiring = false"
                            >Cancelar</Button
                        >
                    </template>
                </template>
            </div>
            <p
                v-if="props.set.status === 'approved'"
                class="text-sm text-muted-foreground"
            >
                Al ponerlo vigente se retira el set real que esté vigente. Los
                antibiogramas ya interpretados conservan su set.
            </p>
        </section>

        <ul
            v-if="errors.length"
            class="list-disc rounded-md border border-red-500/50 bg-red-50 p-3 pl-8 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
            data-test="breakpoint-errors"
        >
            <template v-for="(e, i) in errors" :key="i">
                <li v-for="line in [e].flat()" :key="line">{{ line }}</li>
            </template>
        </ul>

        <section v-if="editable" class="grid gap-2 rounded-lg border p-4">
            <h2 class="font-medium">Importar desde CSV</h2>
            <p class="text-sm text-muted-foreground">
                Columnas:
                <code
                    >organism_group;antibiotic_code;method;susceptible;resistant</code
                >. Método <code>disk</code> (halo, mm: S si ≥ valor S, R si ≤
                valor R) o <code>mic</code> (µg/mL: S si ≤ valor S, R si ≥ valor
                R). Las filas sin valores se ignoran. Reemplaza todo lo cargado;
                si una fila está mal no se guarda nada.
            </p>
            <form
                class="flex flex-wrap items-center gap-2"
                @submit.prevent="sendFile"
            >
                <input
                    type="file"
                    accept=".csv,text/csv"
                    class="text-sm"
                    data-test="csv-file"
                    @change="
                        upload.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <Button
                    type="submit"
                    :disabled="!upload.file || upload.processing"
                >
                    <Spinner v-if="upload.processing" /><Upload
                        v-else
                        class="size-4"
                    />
                    Importar
                </Button>
            </form>
        </section>

        <section class="grid gap-2 rounded-lg border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">
                    {{ table.rows.length }} puntos de corte
                </h2>
                <Input
                    v-model="filter"
                    class="max-w-xs"
                    placeholder="Filtrar por grupo o antibiótico"
                />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-2 py-2 font-medium">Grupo</th>
                            <th class="px-2 py-2 font-medium">Antibiótico</th>
                            <th class="px-2 py-2 font-medium">Método</th>
                            <th class="px-2 py-2 font-medium">S</th>
                            <th class="px-2 py-2 font-medium">R</th>
                            <th v-if="editable"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="{ row, i } in visibleRows"
                            :key="i"
                            class="border-t"
                        >
                            <template v-if="editable">
                                <td class="px-2 py-1">
                                    <select
                                        v-model="row.organism_group"
                                        :class="inputClass"
                                        aria-label="Grupo"
                                    >
                                        <option
                                            v-for="g in props.groups"
                                            :key="g.value"
                                            :value="g.value"
                                        >
                                            {{ g.label }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <select
                                        v-model="row.antibiotic_code"
                                        :class="inputClass"
                                        aria-label="Antibiótico"
                                    >
                                        <option
                                            v-for="a in props.antibiotics"
                                            :key="a.code"
                                            :value="a.code"
                                        >
                                            {{ a.name }} ({{ a.code }})
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <select
                                        v-model="row.method"
                                        :class="inputClass"
                                        aria-label="Método"
                                    >
                                        <option
                                            v-for="m in props.methods"
                                            :key="m.value"
                                            :value="m.value"
                                        >
                                            {{ m.label }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <input
                                        v-model="row.susceptible"
                                        :class="inputClass"
                                        class="w-20"
                                        inputmode="decimal"
                                        aria-label="S"
                                    />
                                </td>
                                <td class="px-2 py-1">
                                    <input
                                        v-model="row.resistant"
                                        :class="inputClass"
                                        class="w-20"
                                        inputmode="decimal"
                                        aria-label="R"
                                    />
                                </td>
                                <td class="px-2 py-1">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        aria-label="Quitar"
                                        @click="table.rows.splice(i, 1)"
                                        ><Trash2 class="size-4"
                                    /></Button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="px-2 py-1">
                                    {{ groupLabel(row.organism_group) }}
                                </td>
                                <td class="px-2 py-1">
                                    {{
                                        props.set.breakpoints[i]?.antibiotic ??
                                        row.antibiotic_code
                                    }}
                                </td>
                                <td class="px-2 py-1">
                                    {{ methodLabel(row.method) }}
                                </td>
                                <td class="px-2 py-1 tabular-nums">
                                    {{ row.susceptible }}
                                </td>
                                <td class="px-2 py-1 tabular-nums">
                                    {{ row.resistant }}
                                </td>
                            </template>
                        </tr>
                        <tr v-if="!table.rows.length">
                            <td
                                colspan="6"
                                class="px-3 py-6 text-center text-muted-foreground"
                            >
                                Sin puntos de corte cargados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="editable" class="flex flex-wrap justify-between gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        table.rows.push({
                            organism_group: props.groups[0]?.value ?? '',
                            antibiotic_code: props.antibiotics[0]?.code ?? '',
                            method: 'disk',
                            susceptible: '',
                            resistant: '',
                        })
                    "
                    ><Plus class="size-4" /> Agregar fila</Button
                >
                <Button
                    :disabled="table.processing || !table.isDirty"
                    data-test="save-breakpoints"
                    @click="saveTable"
                >
                    <Spinner v-if="table.processing" /> Guardar tabla
                </Button>
            </div>
        </section>
    </div>
</template>
