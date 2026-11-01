<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Dna } from '@lucide/vue';
import { computed, watch } from 'vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { store as storeIdentification } from '@/routes/identifications';
import type { SampleHeader } from '@/types/clinical';

type Organism = {
    id: number;
    name: string;
    group: string;
    group_label: string;
    has_panel: boolean;
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
    };
    history: {
        organism: string;
        method: string;
        level: string;
        tests: string | null;
        identified_at: string;
    }[];
    organisms: Organism[];
    methods: { value: string; label: string }[];
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

const form = useForm({
    organism_id: null as number | null,
    method: props.methods[0]?.value ?? 'biochemical',
    level: 'definitive',
    tests: '',
    confidence: '',
    requires_susceptibility: true,
    no_ast_reason: '',
});

const organism = computed(() =>
    props.organisms.find((o) => o.id === form.organism_id),
);

// Si el grupo no tiene panel, no se puede pedir antibiograma.
watch(organism, (value) => {
    if (value && !value.has_panel) {
        form.requires_susceptibility = false;
    }
});

function submit(level: 'preliminary' | 'definitive') {
    form.level = level;
    form.post(storeIdentification(props.isolate.ulid).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Identificación ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form
                class="grid content-start gap-5 rounded-lg border p-4"
                @submit.prevent="submit('definitive')"
            >
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        <Dna class="size-5" /> Aislamiento
                        {{ props.isolate.number }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ props.isolate.morphotype }}
                        <template v-if="props.isolate.plate">
                            · {{ props.isolate.plate }}</template
                        >
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <Label for="method">Método</Label>
                        <select
                            id="method"
                            v-model="form.method"
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
                        <InputError :message="form.errors.method" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="organism">Organismo</Label>
                        <select
                            id="organism"
                            v-model="form.organism_id"
                            class="h-9 rounded-md border bg-background px-2 text-sm"
                            data-test="organism"
                        >
                            <option :value="null" disabled>
                                Elegí el organismo
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
                    <Label for="tests"
                        >Pruebas realizadas / resultado del equipo</Label
                    >
                    <textarea
                        id="tests"
                        v-model="form.tests"
                        rows="3"
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                        placeholder="p. ej., TSI A/A, indol +, citrato −"
                    />
                </div>
                <div v-if="form.method === 'vitek'" class="grid gap-1 sm:w-1/2">
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
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="form.requires_susceptibility"
                            type="checkbox"
                            class="size-4"
                            :disabled="
                                organism !== undefined && !organism.has_panel
                            "
                        />
                        Requiere antibiograma
                    </label>
                    <p
                        v-if="organism && !organism.has_panel"
                        class="text-xs text-muted-foreground"
                    >
                        No hay panel configurado para
                        {{ organism.group_label }}.
                    </p>
                    <InputError
                        :message="form.errors.requires_susceptibility"
                    />
                    <div
                        v-if="!form.requires_susceptibility"
                        class="grid gap-1"
                    >
                        <Label for="no_ast_reason"
                            >Motivo por el que no requiere antibiograma</Label
                        >
                        <Input
                            id="no_ast_reason"
                            v-model="form.no_ast_reason"
                        />
                        <InputError :message="form.errors.no_ast_reason" />
                    </div>
                </fieldset>

                <div class="flex flex-wrap justify-end gap-2">
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
                        <em>{{ item.organism }}</em> · {{ item.level }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ item.method }} ·
                        {{ formatDateTime(item.identified_at) }}
                    </div>
                    <div v-if="item.tests" class="text-xs">
                        {{ item.tests }}
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
