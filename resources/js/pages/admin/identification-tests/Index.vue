<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, store, update } from '@/routes/admin/identification-tests';

type CodeLabel = { code: string; label: string };
type Test = {
    id: number;
    code: string;
    name: string;
    kind: string;
    kind_label: string;
    result_options: CodeLabel[];
    incubation_hours: number | null;
    is_reportable: boolean;
    is_active: boolean;
    is_reference_template: boolean;
    lock_version: number;
};

const props = defineProps<{
    tests: Test[];
    kinds: { value: string; label: string }[];
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pruebas de identificación', href: index() }],
    },
});

const byKind = computed(() =>
    props.kinds.map((k) => ({
        ...k,
        tests: props.tests.filter((t) => t.kind === k.value),
    })),
);

const editing = ref<number | 'new' | null>(null);
const blank = () => ({
    code: '',
    name: '',
    kind: 'rapid',
    incubation_hours: null as number | null,
    is_reportable: false,
    is_active: true,
    result_options: [
        { code: '', label: '' },
        { code: '', label: '' },
    ] as CodeLabel[],
    lock_version: 0,
});
const form = useForm(blank());

function startNew() {
    editing.value = 'new';
    form.defaults(blank());
    form.reset();
    form.clearErrors();
}

function startEdit(test: Test) {
    editing.value = test.id;
    form.defaults({
        code: test.code,
        name: test.name,
        kind: test.kind,
        incubation_hours: test.incubation_hours,
        is_reportable: test.is_reportable,
        is_active: test.is_active,
        result_options: test.result_options.map((o) => ({ ...o })),
        lock_version: test.lock_version,
    });
    form.reset();
    form.clearErrors();
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
const optionError = computed(
    () =>
        errors.value.result_options ??
        Object.entries(errors.value).find(([k]) =>
            k.startsWith('result_options.'),
        )?.[1],
);
</script>

<template>
    <Head title="Pruebas de identificación" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="max-w-3xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Pruebas de identificación
                </h1>
                <p class="text-sm text-muted-foreground">
                    El Gram de la colonia, las pruebas rápidas, las bioquímicas,
                    las complementarias y las de mecanismo que se registran en
                    cada aislamiento, con sus resultados posibles. Sin
                    incubación, la prueba se lee en el momento; con incubación,
                    queda sembrada y aparece en la mesada para leer. Las
                    marcadas «se informa» salen en el informe. Los resultados ya
                    registrados guardan su texto: editar el catálogo no cambia
                    el historial.
                </p>
                <p
                    v-if="!props.can.edit"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Las pruebas las define el Director técnico responsable.
                </p>
            </div>
            <Button
                v-if="props.can.edit && editing === null"
                data-test="new-test"
                @click="startNew"
                ><Plus class="size-4" /> Prueba nueva</Button
            >
        </div>

        <form
            v-if="editing !== null"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="save"
        >
            <div class="grid gap-3 sm:grid-cols-[10rem_1fr_14rem]">
                <label class="grid gap-1 text-sm">
                    Código
                    <Input
                        v-model="form.code"
                        :disabled="editing !== 'new'"
                        maxlength="30"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Nombre
                    <Input v-model="form.name" data-test="test-name" />
                </label>
                <label class="grid gap-1 text-sm">
                    Para qué se usa
                    <select
                        v-model="form.kind"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option
                            v-for="k in props.kinds"
                            :key="k.value"
                            :value="k.value"
                        >
                            {{ k.label }}
                        </option>
                    </select>
                </label>
            </div>
            <InputError
                :message="
                    errors.code ??
                    errors.name ??
                    errors.kind ??
                    errors.lock_version
                "
            />
            <div class="flex flex-wrap items-center gap-6 text-sm">
                <span class="flex items-center gap-2">
                    Incubación
                    <input
                        v-model.number="form.incubation_hours"
                        type="number"
                        min="0"
                        class="h-8 w-20 rounded-md border bg-background px-2"
                        placeholder="—"
                        aria-label="Horas de incubación"
                    />
                    h (vacío: se lee en el momento)
                </span>
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_reportable"
                        type="checkbox"
                        class="size-4"
                    />
                    Se informa
                </label>
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4"
                    />
                    Activa
                </label>
            </div>
            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Resultados posibles</legend>
                <div
                    v-for="(option, i) in form.result_options"
                    :key="i"
                    class="flex gap-2"
                >
                    <Input
                        v-model="option.code"
                        class="w-28"
                        placeholder="Código"
                        aria-label="Código del resultado"
                    />
                    <Input
                        v-model="option.label"
                        class="flex-1"
                        placeholder="Texto (p. ej., Positiva)"
                        aria-label="Texto del resultado"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Quitar resultado"
                        @click="form.result_options.splice(i, 1)"
                        ><X class="size-4"
                    /></Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="w-fit"
                    @click="form.result_options.push({ code: '', label: '' })"
                    ><Plus class="size-4" /> Resultado</Button
                >
                <InputError :message="optionError" />
            </fieldset>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" @click="editing = null"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-test"
                    >Guardar</Button
                >
            </div>
        </form>

        <section v-for="group in byKind" :key="group.value" class="grid gap-2">
            <h2 class="font-medium">{{ group.label }}</h2>
            <p v-if="!group.tests.length" class="text-sm text-muted-foreground">
                Sin pruebas.
            </p>
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="test in group.tests"
                    :key="test.id"
                    class="grid gap-1 rounded-lg border p-3 text-sm"
                    :class="{ 'opacity-60': !test.is_active }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-medium">
                            {{ test.name }}
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ test.code }}</span
                            >
                        </div>
                        <Button
                            v-if="props.can.edit && editing === null"
                            size="sm"
                            variant="ghost"
                            @click="startEdit(test)"
                            >Editar</Button
                        >
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <Badge v-if="test.incubation_hours" variant="outline"
                            >{{ test.incubation_hours }} h</Badge
                        >
                        <Badge v-else variant="outline">En el momento</Badge>
                        <Badge v-if="test.is_reportable">Se informa</Badge>
                        <Badge
                            v-if="test.is_reference_template"
                            variant="secondary"
                            >Plantilla de referencia</Badge
                        >
                        <Badge v-if="!test.is_active" variant="secondary"
                            >Inactiva</Badge
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            test.result_options.map((o) => o.label).join(' · ')
                        }}
                    </p>
                </article>
            </div>
        </section>
    </div>
</template>
