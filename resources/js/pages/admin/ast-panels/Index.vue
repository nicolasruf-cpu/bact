<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, TriangleAlert, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, store, update } from '@/routes/admin/ast-panels';

type Panel = {
    id: number;
    code: string;
    name: string;
    is_vitek: boolean;
    read_min_hours: number | null;
    read_due_hours: number | null;
    is_active: boolean;
    is_reference_template: boolean;
    organism_groups: string[];
    sample_type_ids: number[];
    antibiotic_ids: number[];
    lock_version: number;
};
type Option = { id: number; code: string; name: string; is_active: boolean };

const props = defineProps<{
    panels: Panel[];
    organismGroups: { value: string; label: string }[];
    sampleTypes: Option[];
    antibiotics: Option[];
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Paneles de antibiograma', href: index() }],
    },
});

const groupLabel = (value: string) =>
    props.organismGroups.find((g) => g.value === value)?.label ?? value;
const sampleName = (id: number) =>
    props.sampleTypes.find((s) => s.id === id)?.name ?? `#${id}`;
const antibiotic = (id: number) => props.antibiotics.find((a) => a.id === id);
const samplesLabel = (panel: Panel) =>
    panel.sample_type_ids.length
        ? panel.sample_type_ids.map(sampleName).join(', ')
        : 'Todas las muestras';

// --- Cobertura: qué panel le toca a cada grupo -------------------------------
const coverage = computed(() =>
    props.organismGroups.map((group) => {
        const panels = props.panels.filter(
            (p) => p.is_active && p.organism_groups.includes(group.value),
        );
        return {
            ...group,
            panels,
            onlySome:
                panels.length > 0 &&
                panels.every((p) => p.sample_type_ids.length > 0),
        };
    }),
);
const notes = computed(() => [
    ...coverage.value
        .filter((g) => g.panels.length === 0)
        .map(
            (g) =>
                `${g.label}: no tiene panel. Los gérmenes de este grupo solo se pueden identificar sin antibiograma, indicando el motivo.`,
        ),
    ...coverage.value
        .filter((g) => g.onlySome)
        .map(
            (g) =>
                `${g.label}: tiene panel solo para algunas muestras. En las demás no se va a poder pedir antibiograma.`,
        ),
]);

// --- Editor -----------------------------------------------------------------
const editing = ref<number | 'new' | null>(null);
const form = useForm({
    code: '',
    name: '',
    is_vitek: false,
    read_min_hours: null as number | null,
    read_due_hours: null as number | null,
    is_active: true,
    organism_groups: [] as string[],
    sample_type_ids: [] as number[],
    antibiotic_ids: [] as number[],
    lock_version: 0,
});
const toAdd = ref<number | ''>('');

function startNew() {
    editing.value = 'new';
    form.defaults({
        code: '',
        name: '',
        is_vitek: false,
        read_min_hours: null,
        read_due_hours: null,
        is_active: true,
        organism_groups: [],
        sample_type_ids: [],
        antibiotic_ids: [],
        lock_version: 0,
    });
    form.reset();
    form.clearErrors();
}

function startEdit(panel: Panel) {
    editing.value = panel.id;
    form.defaults({
        code: panel.code,
        name: panel.name,
        is_vitek: panel.is_vitek,
        read_min_hours: panel.read_min_hours,
        read_due_hours: panel.read_due_hours,
        is_active: panel.is_active,
        organism_groups: [...panel.organism_groups],
        sample_type_ids: [...panel.sample_type_ids],
        antibiotic_ids: [...panel.antibiotic_ids],
        lock_version: panel.lock_version,
    });
    form.reset();
    form.clearErrors();
}

const available = computed(() =>
    props.antibiotics.filter(
        (a) => a.is_active && !form.antibiotic_ids.includes(a.id),
    ),
);

function addAntibiotic() {
    if (toAdd.value !== '') {
        form.antibiotic_ids.push(toAdd.value);
        toAdd.value = '';
    }
}

function move(index: number, delta: number) {
    const ids = form.antibiotic_ids;
    const target = index + delta;
    if (target < 0 || target >= ids.length) {
        return;
    }
    [ids[index], ids[target]] = [ids[target], ids[index]];
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value === 'new') {
        form.post(store().url, options);
    } else if (editing.value !== null) {
        form.put(update(editing.value).url, options);
    }
}

const errors = computed(() => form.errors as Record<string, string>);
</script>

<template>
    <Head title="Paneles de antibiograma" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="max-w-3xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Paneles de antibiograma
                </h1>
                <p class="text-sm text-muted-foreground">
                    Cada panel vale para uno o más grupos de germen y para una o
                    más muestras. Al preparar el antibiograma se propone el
                    panel definido para la muestra; si no hay, el que vale para
                    todas, y el bioquímico puede elegir otro de los que
                    corresponden. Un panel VITEK es un panel más: se carga como
                    CIM. Los cambios valen para los antibiogramas que se
                    preparen desde ahora; los ya preparados no cambian.
                </p>
                <p
                    v-if="!props.can.edit"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Los paneles los define el Director técnico responsable.
                </p>
            </div>
            <Button
                v-if="props.can.edit && editing === null"
                data-test="new-panel"
                @click="startNew"
                ><Plus class="size-4" /> Panel nuevo</Button
            >
        </div>

        <!-- Revisá -->
        <div
            v-if="notes.length"
            class="grid gap-1 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
        >
            <p class="flex items-center gap-2 font-medium">
                <TriangleAlert class="size-4" /> Revisá
            </p>
            <ul class="list-disc pl-6">
                <li v-for="note in notes" :key="note">{{ note }}</li>
            </ul>
        </div>

        <!-- Editor -->
        <form
            v-if="editing !== null"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="save"
        >
            <h2 class="font-medium">
                {{
                    editing === 'new'
                        ? 'Panel nuevo'
                        : `Modificar el panel ${form.code}`
                }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                <label class="grid gap-1 text-sm">
                    Código
                    <Input
                        v-model="form.code"
                        maxlength="30"
                        :readonly="editing !== 'new'"
                        required
                        data-test="panel-code"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Nombre
                    <Input
                        v-model="form.name"
                        required
                        data-test="panel-name"
                    />
                </label>
            </div>
            <InputError :message="form.errors.code ?? form.errors.name" />
            <InputError
                :message="
                    form.errors.read_min_hours ?? form.errors.read_due_hours
                "
            />
            <div class="flex flex-wrap gap-6 text-sm">
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_vitek"
                        type="checkbox"
                        class="size-4"
                    />
                    Panel VITEK (se carga como CIM)
                </label>
                <span class="flex flex-wrap items-center gap-2">
                    Se lee desde las
                    <input
                        v-model.number="form.read_min_hours"
                        type="number"
                        min="0"
                        class="h-8 w-20 rounded-md border bg-background px-2"
                        placeholder="—"
                        aria-label="Se lee desde (h)"
                    />
                    h y vence a las
                    <input
                        v-model.number="form.read_due_hours"
                        type="number"
                        min="0"
                        class="h-8 w-20 rounded-md border bg-background px-2"
                        placeholder="—"
                        aria-label="Vence a las (h)"
                    />
                    h de montado. Vacío: las del protocolo.
                </span>
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Activo
                </label>
            </div>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Grupos de germen</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <label
                        v-for="group in props.organismGroups"
                        :key="group.value"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="form.organism_groups"
                            type="checkbox"
                            :value="group.value"
                            class="size-4"
                        />
                        {{ group.label }}
                    </label>
                </div>
                <InputError :message="form.errors.organism_groups" />
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Muestras</legend>
                <p class="text-xs text-muted-foreground">
                    Sin ninguna tildada, el panel vale para todas las muestras.
                    Si hay un panel para la muestra, se propone ese antes que el
                    general.
                </p>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <label
                        v-for="sample in props.sampleTypes"
                        :key="sample.id"
                        class="flex items-center gap-2"
                        :class="{ 'text-muted-foreground': !sample.is_active }"
                    >
                        <input
                            v-model="form.sample_type_ids"
                            type="checkbox"
                            :value="sample.id"
                            class="size-4"
                        />
                        {{ sample.name }}
                    </label>
                </div>
                <InputError :message="form.errors.sample_type_ids" />
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">
                    Antibióticos, en el orden en que se cargan y se informan
                </legend>
                <ol class="grid gap-1 text-sm">
                    <li
                        v-for="(id, i) in form.antibiotic_ids"
                        :key="id"
                        class="flex items-center gap-2 rounded-md border px-2 py-1"
                    >
                        <span class="w-6 text-right text-muted-foreground"
                            >{{ i + 1 }}.</span
                        >
                        <span class="w-12 font-mono">{{
                            antibiotic(id)?.code
                        }}</span>
                        <span class="flex-1">{{ antibiotic(id)?.name }}</span>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="i === 0"
                            aria-label="Subir"
                            @click="move(i, -1)"
                            ><ArrowUp class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="i === form.antibiotic_ids.length - 1"
                            aria-label="Bajar"
                            @click="move(i, 1)"
                            ><ArrowDown class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            aria-label="Quitar"
                            @click="form.antibiotic_ids.splice(i, 1)"
                            ><X class="size-4"
                        /></Button>
                    </li>
                </ol>
                <div class="flex gap-2">
                    <select
                        v-model="toAdd"
                        class="h-9 max-w-sm flex-1 rounded-md border bg-background px-2 text-sm"
                        data-test="add-antibiotic"
                        @change="addAntibiotic"
                    >
                        <option value="">Agregar antibiótico…</option>
                        <option
                            v-for="a in available"
                            :key="a.id"
                            :value="a.id"
                        >
                            {{ a.code }} — {{ a.name }}
                        </option>
                    </select>
                </div>
                <p class="text-xs text-muted-foreground">
                    Si falta un antibiótico, se da de alta en Catálogos →
                    Antibióticos.
                </p>
                <InputError :message="form.errors.antibiotic_ids" />
            </fieldset>

            <InputError :message="errors.lock_version" />
            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" @click="editing = null"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-panel"
                    >Guardar panel</Button
                >
            </div>
        </form>

        <!-- Qué panel le toca a cada grupo -->
        <section class="grid gap-2">
            <h2 class="font-medium">Qué panel le toca a cada grupo</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">Grupo</th>
                            <th class="px-3 py-2 font-medium">
                                Paneles (y muestras)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="group in coverage"
                            :key="group.value"
                            class="border-t align-top"
                        >
                            <td class="px-3 py-2">{{ group.label }}</td>
                            <td class="px-3 py-2">
                                <ul v-if="group.panels.length">
                                    <li
                                        v-for="panel in group.panels"
                                        :key="panel.id"
                                    >
                                        {{ panel.name }}
                                        <span v-if="panel.is_vitek">
                                            · VITEK</span
                                        >
                                        <span class="text-muted-foreground">
                                            — {{ samplesLabel(panel) }}</span
                                        >
                                    </li>
                                </ul>
                                <span v-else class="text-amber-700"
                                    >Sin panel</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Paneles -->
        <section class="grid gap-2">
            <h2 class="font-medium">Paneles</h2>
            <article
                v-for="panel in props.panels"
                :key="panel.id"
                class="grid gap-2 rounded-lg border p-4"
                :class="{ 'text-muted-foreground': !panel.is_active }"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-medium">{{ panel.name }}</h3>
                    <span class="font-mono text-xs">{{ panel.code }}</span>
                    <Badge v-if="panel.is_vitek">VITEK</Badge>
                    <Badge v-if="panel.is_reference_template" variant="outline"
                        >Plantilla de referencia, sin revisar</Badge
                    >
                    <Badge v-if="!panel.is_active" variant="secondary"
                        >Inactivo</Badge
                    >
                    <Button
                        v-if="props.can.edit && editing === null"
                        size="sm"
                        variant="ghost"
                        class="ml-auto"
                        @click="startEdit(panel)"
                        >Modificar</Button
                    >
                </div>
                <dl class="grid gap-1 text-sm sm:grid-cols-[8rem_1fr]">
                    <dt class="text-muted-foreground">Grupos</dt>
                    <dd>
                        {{ panel.organism_groups.map(groupLabel).join(', ') }}
                    </dd>
                    <dt class="text-muted-foreground">Muestras</dt>
                    <dd>{{ samplesLabel(panel) }}</dd>
                    <dt class="text-muted-foreground">
                        Antibióticos ({{ panel.antibiotic_ids.length }})
                    </dt>
                    <dd>
                        {{
                            panel.antibiotic_ids
                                .map((id) => antibiotic(id)?.name ?? id)
                                .join(' · ')
                        }}
                    </dd>
                </dl>
            </article>
            <p
                v-if="!props.panels.length"
                class="text-sm text-muted-foreground"
            >
                Todavía no hay paneles.
            </p>
        </section>
    </div>
</template>
