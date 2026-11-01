<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Microscope, Plus, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { store as storeReading } from '@/routes/readings';
import type { SampleHeader } from '@/types/clinical';

type Option = { code: string; label: string; outcome: string };
type Category = { code: string; label: string };
type Plate = { id: number; medium: string; code: string; inoculum: string };

const props = defineProps<{
    sample: SampleHeader;
    culture: {
        ulid: string;
        status: string;
        status_label: string;
        inoculated_at: string;
        available_at: string;
        attempt: number;
        plates: Plate[];
        readings: {
            attempt: number;
            label: string;
            notes: string | null;
            read_at: string;
        }[];
    };
    options: Option[];
    countCategories: Category[];
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
    is_significant: boolean;
};

const form = useForm({
    option_code: '',
    notes: '',
    isolates: [] as IsolateRow[],
});

const selected = computed(() =>
    props.options.find((o) => o.code === form.option_code),
);
const isGrowth = computed(() => selected.value?.outcome === 'growth');
const available = computed(
    () => new Date(props.culture.available_at) <= new Date(),
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
        plate_id: props.culture.plates[0]?.id ?? null,
        is_significant: true,
    });
}

function submit() {
    form.transform((data) => ({
        ...data,
        isolates: isGrowth.value ? data.isolates : [],
    })).post(storeReading(props.culture.ulid).url);
}

function errorFor(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[
        `isolates.${index}.${field}`
    ];
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
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <Microscope class="size-5" /> Lectura n.º
                    {{ props.culture.attempt }}
                </h1>

                <p
                    v-if="!available"
                    class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    La lectura estará disponible desde
                    {{ formatDateTime(props.culture.available_at) }} (incubación
                    mínima del protocolo).
                </p>

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

                <fieldset v-if="isGrowth" class="grid gap-3">
                    <legend class="mb-1 text-sm font-medium">
                        Aislamientos
                    </legend>
                    <p class="text-xs text-muted-foreground">
                        Marcá como significativos los que se identifican. La
                        significancia la decide el bacteriólogo.
                    </p>
                    <div
                        v-for="(isolate, index) in form.isolates"
                        :key="index"
                        class="grid gap-3 rounded-md border p-3"
                        data-test="isolate-row"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium"
                                >Aislamiento {{ index + 1 }}</span
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
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
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
                                        v-for="plate in props.culture.plates"
                                        :key="plate.id"
                                        :value="plate.id"
                                    >
                                        {{ plate.medium }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="isolate.is_significant"
                                type="checkbox"
                                class="size-4"
                            />
                            Significativo (pasa a identificación)
                        </label>
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

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="
                            !form.option_code || form.processing || !available
                        "
                        data-test="save-reading"
                    >
                        <Spinner v-if="form.processing" /> Registrar lectura
                    </Button>
                </div>
            </form>

            <aside
                class="grid content-start gap-3 rounded-lg border p-4 text-sm"
            >
                <h2 class="font-medium">Cultivo</h2>
                <p>
                    Sembrado {{ formatDateTime(props.culture.inoculated_at) }}
                </p>
                <ul class="grid gap-1">
                    <li
                        v-for="plate in props.culture.plates"
                        :key="plate.id"
                        class="text-muted-foreground"
                    >
                        {{ plate.medium }} · {{ plate.inoculum }}
                    </li>
                </ul>
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
