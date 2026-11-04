<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Ban, Microscope, Plus, Route, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import EarlyTimeWarning from '@/components/domain/EarlyTimeWarning.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { create as identifyRoute } from '@/routes/identifications';
import { show as marchRoute } from '@/routes/marches';
import { lost as plateLostRoute } from '@/routes/plates';
import { store as storeReading } from '@/routes/readings';
import { reject as rejectRoute } from '@/routes/sample-studies';
import type { SampleHeader } from '@/types/clinical';

type Option = { code: string; label: string; outcome: string };
type Category = { code: string; label: string };
type ValueLabel = { value: string; label: string };
type Plate = {
    id: number;
    medium: string;
    code: string;
    inoculum: string;
    status: string;
    status_label: string;
    lost_reason: string | null;
    is_resown: boolean;
    due: boolean;
    hours: string | null;
};

const props = defineProps<{
    sample: SampleHeader;
    culture: {
        ulid: string;
        status: string;
        status_label: string;
        inoculated_at: string;
        available_at: string;
        attempt: number;
        step: number;
        steps: number;
        step_hours: number;
        is_last: boolean;
        in_workup: boolean;
        pending_enrichments: string[];
        enrichment_url: string | null;
        plates: Plate[];
        isolates: {
            ulid: string;
            number: number;
            morphotype: string;
            is_significant: boolean;
            significance: string;
            significance_label: string;
        }[];
        readings: {
            attempt: number;
            label: string;
            notes: string | null;
            read_at: string;
            plates: {
                medium: string | null;
                observation: string;
                notes: string | null;
            }[];
        }[];
        study_id: number;
    };
    options: Option[];
    countCategories: Category[];
    observations: ValueLabel[];
    significances: ValueLabel[];
    can: { negative: boolean; positive: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mesada de lectura', href: '/workbenches/reading' },
            { title: 'Lectura', href: '#' },
        ],
    },
});

type IsolateRow = {
    morphotype: string;
    count_category: string | null;
    plate_id: number | null;
    significance: string;
};

const duePlates = computed(() => props.culture.plates.filter((p) => p.due));

const form = useForm({
    option_code: '',
    notes: '',
    isolates: [] as IsolateRow[],
    plates: [] as { plate_id: number; observation: string; notes: string }[],
    count_category: null as string | null,
    early_confirmed: false,
    early_reason: '',
});

const selected = computed(() =>
    props.options.find((o) => o.code === form.option_code),
);
const isGrowth = computed(() => selected.value?.outcome === 'growth');
const takesCount = computed(
    () =>
        selected.value?.outcome === 'not_significant' ||
        selected.value?.outcome === 'mixed_flora',
);
const available = computed(
    () => new Date(props.culture.available_at) <= new Date(),
);

// Lo que se vio en cada placa que toca leer (A3). Se ajusta solo con la opción elegida.
const observation = reactive<Record<number, string>>({});
const plateNotes = reactive<Record<number, string>>({});
watch(
    () => selected.value?.outcome,
    (outcome) => {
        for (const plate of duePlates.value) {
            if (outcome === 'no_growth') {
                observation[plate.id] = props.culture.in_workup
                    ? 'no_new_growth'
                    : 'no_growth';
            } else if (outcome === 'growth' && !observation[plate.id]) {
                observation[plate.id] = props.culture.in_workup
                    ? 'no_new_growth'
                    : 'no_growth';
            }
        }
    },
);
const plateObservations = computed(() =>
    props.observations.filter(
        (o) => o.value !== 'no_new_growth' || props.culture.in_workup,
    ),
);
const growthPlates = computed(() =>
    duePlates.value.filter((p) => observation[p.id] === 'growth'),
);

function allowed(option: Option): boolean {
    return option.outcome === 'no_growth'
        ? props.can.negative
        : props.can.positive;
}

function choose(option: Option) {
    if (!allowed(option)) {
        return;
    }
    form.option_code = option.code;
    if (option.outcome === 'growth' && form.isolates.length === 0) {
        addIsolate();
    }
}

function addIsolate() {
    form.isolates.push({
        morphotype: '',
        count_category: null,
        plate_id: growthPlates.value[0]?.id ?? duePlates.value[0]?.id ?? null,
        significance: 'significant',
    });
}

// Un aislamiento sale de una placa con desarrollo.
watch(
    () => form.isolates.map((i) => i.plate_id),
    (ids) => {
        for (const id of ids) {
            if (id !== null && duePlates.value.some((p) => p.id === id)) {
                observation[id] = 'growth';
            }
        }
    },
);

function submit() {
    form.transform((data) => ({
        ...data,
        isolates: isGrowth.value ? data.isolates : [],
        count_category: takesCount.value ? data.count_category : null,
        plates: duePlates.value
            .filter((p) => observation[p.id])
            .map((p) => ({
                plate_id: p.id,
                observation: observation[p.id],
                notes: plateNotes[p.id] ?? '',
            })),
    })).post(storeReading(props.culture.ulid).url);
}

function errorFor(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[
        `isolates.${index}.${field}`
    ];
}

// ---- Placa perdida (B7) ----
const lostPlate = ref<number | null>(null);
const lostForm = useForm({ reason: '', resow: true });
function markLost(plate: Plate) {
    lostForm.post(plateLostRoute(plate.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            lostPlate.value = null;
            lostForm.reset();
        },
    });
}

// ---- Muestra no apta (B8) ----
const rejecting = ref(false);
const rejectForm = useForm({ reason: '', new_sample_requested_to: '' });
function reject() {
    rejectForm.post(rejectRoute(props.culture.study_id).url);
}

// Teclas numéricas para elegir la opción de lectura (UX_UI.md).
function onKey(event: KeyboardEvent) {
    const target = event.target as HTMLElement;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
        return;
    }
    const index = Number(event.key) - 1;
    if (index >= 0 && index < props.options.length) {
        choose(props.options[index]);
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="`Lectura ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form
                class="grid content-start gap-5 rounded-lg border p-4"
                @submit.prevent="submit"
            >
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        <Microscope class="size-5" /> Lectura n.º
                        {{ props.culture.attempt }}
                    </h1>
                    <p
                        v-if="props.culture.steps > 1"
                        class="text-sm text-muted-foreground"
                        data-test="reading-step"
                    >
                        Lectura programada {{ props.culture.step }} de
                        {{ props.culture.steps }} (a las
                        {{ props.culture.step_hours }} h de la siembra).
                        <template v-if="!props.culture.is_last"
                            >Sin desarrollo programa la siguiente.</template
                        >
                        <template v-else>Es la última del protocolo.</template>
                    </p>
                </div>

                <p
                    v-if="props.culture.in_workup"
                    class="rounded-md border bg-muted/40 p-3 text-sm"
                    data-test="workup-notice"
                >
                    El cultivo ya tiene
                    {{ props.culture.isolates.length }} aislamiento(s) en
                    estudio. Registrá si hay desarrollo nuevo o sin desarrollo
                    nuevo.
                </p>

                <p
                    v-if="
                        props.culture.is_last &&
                        props.culture.pending_enrichments.length
                    "
                    class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                    data-test="pending-enrichment"
                >
                    Falta repicar el caldo ({{
                        props.culture.pending_enrichments.join(', ')
                    }}) antes de la última lectura.
                    <Link
                        v-if="props.culture.enrichment_url"
                        :href="props.culture.enrichment_url"
                        class="font-medium underline"
                        >Ir al repique</Link
                    >
                </p>

                <EarlyTimeWarning
                    v-if="!available"
                    v-model:confirmed="form.early_confirmed"
                    v-model:reason="form.early_reason"
                    :available-at="props.culture.available_at"
                    :error="form.errors.early_confirmed"
                />

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        Resultado de la lectura
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="(option, index) in props.options"
                            :key="option.code"
                            type="button"
                            class="flex items-center gap-3 rounded-md border p-3 text-left text-sm disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                form.option_code === option.code
                                    ? 'border-primary bg-primary/10 ring-1 ring-primary'
                                    : 'hover:border-primary'
                            "
                            :disabled="!allowed(option)"
                            :aria-pressed="form.option_code === option.code"
                            :data-test="`option-${option.code}`"
                            @click="choose(option)"
                        >
                            <kbd
                                class="rounded border px-1.5 text-xs text-muted-foreground"
                                >{{ index + 1 }}</kbd
                            >
                            {{ option.label }}
                        </button>
                    </div>
                    <InputError :message="form.errors.option_code" />
                </fieldset>

                <fieldset
                    v-if="selected && duePlates.length"
                    class="grid gap-2"
                    data-test="plate-observations"
                >
                    <legend class="mb-1 text-sm font-medium">
                        Qué se vio en cada placa
                    </legend>
                    <div
                        v-for="plate in duePlates"
                        :key="plate.id"
                        class="grid gap-2 rounded-md border p-2 sm:grid-cols-[12rem_1fr]"
                    >
                        <span class="text-sm font-medium"
                            >{{ plate.medium
                            }}<span
                                v-if="plate.is_resown"
                                class="font-normal text-muted-foreground"
                            >
                                (resiembra)</span
                            ></span
                        >
                        <div class="flex flex-wrap items-center gap-2">
                            <label
                                v-for="o in plateObservations"
                                :key="o.value"
                                class="flex items-center gap-1 text-sm"
                            >
                                <input
                                    v-model="observation[plate.id]"
                                    type="radio"
                                    :name="`plate-${plate.id}`"
                                    :value="o.value"
                                    :data-test="`plate-${plate.id}-${o.value}`"
                                />
                                {{ o.label }}
                            </label>
                            <Input
                                v-model="plateNotes[plate.id]"
                                class="h-8 min-w-40 flex-1"
                                placeholder="Observación de la placa"
                                :aria-label="`Observación de ${plate.medium}`"
                            />
                        </div>
                    </div>
                    <InputError :message="form.errors.plates" />
                </fieldset>

                <div
                    v-if="takesCount && props.countCategories.length"
                    class="grid gap-1 sm:w-1/2"
                >
                    <Label for="count"
                        >Recuento (opcional, sale en el informe)</Label
                    >
                    <select
                        id="count"
                        v-model="form.count_category"
                        class="h-9 rounded-md border bg-background px-2 text-sm"
                    >
                        <option :value="null">Sin recuento</option>
                        <option
                            v-for="category in props.countCategories"
                            :key="category.code"
                            :value="category.code"
                        >
                            {{ category.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.count_category" />
                </div>

                <fieldset v-if="isGrowth" class="grid gap-3">
                    <legend class="mb-1 text-sm font-medium">
                        Aislamientos
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        Los significativos y los probables contaminantes pasan a
                        identificación (el probable contaminante, sin
                        antibiograma). La significancia la decide el
                        bacteriólogo y se puede cambiar después, con motivo.
                    </p>
                    <div
                        v-for="(isolate, index) in form.isolates"
                        :key="index"
                        class="grid gap-3 rounded-md border p-3"
                        data-test="isolate-row"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium"
                                >Aislamiento
                                {{
                                    props.culture.isolates.length + index + 1
                                }}</span
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Quitar aislamiento ${index + 1}`"
                                @click="form.isolates.splice(index, 1)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`morphotype-${index}`"
                                >Morfotipo / descripción de colonias</Label
                            >
                            <Input
                                :id="`morphotype-${index}`"
                                v-model="isolate.morphotype"
                                placeholder="p. ej., colonias lactosa positivas"
                            />
                            <InputError
                                :message="errorFor(index, 'morphotype')"
                            />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-if="props.countCategories.length"
                                class="grid gap-1"
                            >
                                <Label :for="`count-${index}`">Recuento</Label>
                                <select
                                    :id="`count-${index}`"
                                    v-model="isolate.count_category"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                >
                                    <option :value="null">Sin recuento</option>
                                    <option
                                        v-for="category in props.countCategories"
                                        :key="category.code"
                                        :value="category.code"
                                    >
                                        {{ category.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errorFor(index, 'count_category')"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`plate-${index}`"
                                    >Placa de origen</Label
                                >
                                <select
                                    :id="`plate-${index}`"
                                    v-model="isolate.plate_id"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                >
                                    <option
                                        v-for="plate in duePlates"
                                        :key="plate.id"
                                        :value="plate.id"
                                    >
                                        {{ plate.medium }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`significance-${index}`"
                                    >Significancia</Label
                                >
                                <select
                                    :id="`significance-${index}`"
                                    v-model="isolate.significance"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                    :data-test="`significance-${index}`"
                                >
                                    <option
                                        v-for="s in props.significances"
                                        :key="s.value"
                                        :value="s.value"
                                    >
                                        {{ s.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="w-fit"
                        @click="addIsolate"
                        ><Plus /> Agregar aislamiento</Button
                    >
                    <InputError :message="form.errors.isolates" />
                </fieldset>

                <div class="grid gap-1">
                    <Label for="notes">Observaciones</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="2"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="text-destructive"
                        data-test="reject-sample"
                        @click="rejecting = !rejecting"
                        ><Ban class="size-4" /> Muestra no apta</Button
                    >
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="
                            !form.option_code ||
                            form.processing ||
                            (!available && !form.early_confirmed)
                        "
                        data-test="save-reading"
                    >
                        <Spinner v-if="form.processing" /> Registrar lectura
                    </Button>
                </div>

                <div
                    v-if="rejecting"
                    class="grid gap-2 rounded-md border border-destructive/40 p-3 text-sm"
                    data-test="reject-form"
                >
                    <p class="text-muted-foreground">
                        Cierra el cultivo y el estudio pasa a validación. El
                        informe dice que la muestra no fue apta, con el motivo.
                    </p>
                    <Label for="reject-reason">Motivo</Label>
                    <Input
                        id="reject-reason"
                        v-model="rejectForm.reason"
                        placeholder="p. ej., más de 25 células epiteliales por campo"
                    />
                    <InputError :message="rejectForm.errors.reason" />
                    <Label for="reject-to"
                        >Se pidió una nueva muestra a (opcional)</Label
                    >
                    <Input
                        id="reject-to"
                        v-model="rejectForm.new_sample_requested_to"
                        placeholder="Servicio o persona"
                    />
                    <div class="flex justify-end">
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="rejectForm.processing"
                            @click="reject"
                            >Declarar no apta</Button
                        >
                    </div>
                </div>
            </form>

            <aside
                class="grid content-start gap-3 rounded-lg border p-4 text-sm"
            >
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">Cultivo</h2>
                    <Link
                        :href="marchRoute(props.culture.study_id).url"
                        class="flex items-center gap-1 text-xs underline"
                        ><Route class="size-3" /> Marcha</Link
                    >
                </div>
                <p>
                    Sembrado {{ formatDateTime(props.culture.inoculated_at) }}
                </p>
                <ul class="grid gap-2">
                    <li
                        v-for="plate in props.culture.plates"
                        :key="plate.id"
                        class="grid gap-1"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                :class="
                                    plate.status === 'lost'
                                        ? 'text-muted-foreground line-through'
                                        : ''
                                "
                                >{{ plate.medium }}
                                <span class="text-xs text-muted-foreground"
                                    >· {{ plate.inoculum }}</span
                                ></span
                            >
                            <button
                                v-if="plate.status === 'incubating'"
                                type="button"
                                class="text-xs text-muted-foreground underline"
                                :data-test="`lose-plate-${plate.id}`"
                                @click="
                                    lostPlate =
                                        lostPlate === plate.id ? null : plate.id
                                "
                            >
                                Perdida
                            </button>
                        </div>
                        <span
                            v-if="plate.hours"
                            class="text-xs text-muted-foreground"
                            >Se lee {{ plate.hours }}</span
                        >
                        <span
                            v-if="!plate.due && plate.status === 'incubating'"
                            class="text-xs text-muted-foreground"
                            >No se lee en esta lectura</span
                        >
                        <span
                            v-if="plate.status !== 'incubating'"
                            class="text-xs text-muted-foreground"
                            >{{ plate.status_label
                            }}<template v-if="plate.lost_reason"
                                >: {{ plate.lost_reason }}</template
                            ></span
                        >
                        <div
                            v-if="lostPlate === plate.id"
                            class="grid gap-2 rounded-md border p-2"
                        >
                            <Input
                                v-model="lostForm.reason"
                                placeholder="Motivo (rota, contaminada…)"
                                aria-label="Motivo"
                            />
                            <InputError :message="lostForm.errors.reason" />
                            <label class="flex items-center gap-2 text-xs">
                                <input
                                    v-model="lostForm.resow"
                                    type="checkbox"
                                    class="size-4"
                                />
                                Resembrar desde la muestra
                            </label>
                            <Button
                                type="button"
                                size="sm"
                                :disabled="lostForm.processing"
                                @click="markLost(plate)"
                                >Registrar</Button
                            >
                        </div>
                    </li>
                </ul>
                <template v-if="props.culture.isolates.length">
                    <h2 class="font-medium">Aislamientos</h2>
                    <ul class="grid gap-1">
                        <li
                            v-for="isolate in props.culture.isolates"
                            :key="isolate.number"
                        >
                            <Link
                                :href="identifyRoute(isolate.ulid).url"
                                class="underline"
                                >{{ isolate.number }}.
                                {{ isolate.morphotype }}</Link
                            >
                            <span
                                v-if="isolate.significance !== 'significant'"
                                class="text-xs text-muted-foreground"
                            >
                                ({{ isolate.significance_label }})</span
                            >
                        </li>
                    </ul>
                </template>
                <template v-if="props.culture.readings.length">
                    <h2 class="font-medium">Lecturas anteriores</h2>
                    <ul class="grid gap-1">
                        <li
                            v-for="reading in props.culture.readings"
                            :key="reading.attempt"
                        >
                            <span class="font-medium"
                                >n.º {{ reading.attempt }}:</span
                            >
                            {{ reading.label }}
                            <span class="text-xs text-muted-foreground"
                                >({{ formatDateTime(reading.read_at) }})</span
                            >
                            <div
                                v-for="(p, i) in reading.plates"
                                :key="i"
                                class="text-xs text-muted-foreground"
                            >
                                {{ p.medium }}: {{ p.observation }}
                                <template v-if="p.notes">
                                    — {{ p.notes }}</template
                                >
                            </div>
                            <div
                                v-if="reading.notes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ reading.notes }}
                            </div>
                        </li>
                    </ul>
                </template>
            </aside>
        </div>
    </div>
</template>
