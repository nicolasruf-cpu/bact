<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, store, update } from '@/routes/admin/ast-rules';

type ValueLabel = { value: string; label: string };
type Rule = {
    id: number;
    code: string;
    name: string;
    effect: string;
    effect_label: string;
    interpretation: string | null;
    comment: string | null;
    organism_groups: string[];
    sample_type_ids: number[];
    antibiotic_ids: number[];
    identification_test_id: number | null;
    test_result_code: string | null;
    is_active: boolean;
    lock_version: number;
};

const props = defineProps<{
    rules: Rule[];
    effects: ValueLabel[];
    interpretations: ValueLabel[];
    organismGroups: ValueLabel[];
    sampleTypes: { id: number; name: string }[];
    antibiotics: { id: number; name: string }[];
    tests: {
        id: number;
        name: string;
        options: { code: string; label: string }[];
    }[];
    can: { edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reglas de antibiograma', href: index() }],
    },
});

const editing = ref<number | 'new' | null>(null);
const blank = () => ({
    code: '',
    name: '',
    effect: 'suppress',
    interpretation: null as string | null,
    comment: '',
    organism_groups: [] as string[],
    sample_type_ids: [] as number[],
    antibiotic_ids: [] as number[],
    identification_test_id: null as number | null,
    test_result_code: null as string | null,
    is_active: true,
    lock_version: 0,
});
const form = useForm(blank());

function startNew() {
    editing.value = 'new';
    form.defaults(blank());
    form.reset();
    form.clearErrors();
}

function startEdit(rule: Rule) {
    editing.value = rule.id;
    form.defaults({
        code: rule.code,
        name: rule.name,
        effect: rule.effect,
        interpretation: rule.interpretation,
        comment: rule.comment ?? '',
        organism_groups: [...rule.organism_groups],
        sample_type_ids: [...rule.sample_type_ids],
        antibiotic_ids: [...rule.antibiotic_ids],
        identification_test_id: rule.identification_test_id,
        test_result_code: rule.test_result_code,
        is_active: rule.is_active,
        lock_version: rule.lock_version,
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
const selectedTest = computed(() =>
    props.tests.find((t) => t.id === form.identification_test_id),
);
const groupLabel = (v: string) =>
    props.organismGroups.find((g) => g.value === v)?.label ?? v;
const sampleName = (id: number) =>
    props.sampleTypes.find((s) => s.id === id)?.name ?? `#${id}`;
const antibioticName = (id: number) =>
    props.antibiotics.find((a) => a.id === id)?.name ?? `#${id}`;
const testName = (id: number | null) =>
    props.tests.find((t) => t.id === id)?.name ?? '';
const resultLabel = (testId: number | null, code: string | null) =>
    props.tests
        .find((t) => t.id === testId)
        ?.options.find((o) => o.code === code)?.label ?? code;
</script>

<template>
    <Head title="Reglas de antibiograma" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="max-w-3xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Reglas de antibiograma
                </h1>
                <p class="text-sm text-muted-foreground">
                    Informe selectivo, comentarios y reglas expertas que define
                    el laboratorio. Cada regla vale para uno o más grupos de
                    germen y muestras (ninguna tildada = todas) y, si se elige,
                    solo cuando una prueba del aislamiento dio cierto resultado
                    (p. ej., sinergia BLEE positiva). Se aplica al leer el
                    antibiograma y queda anotada en cada resultado; el
                    bacteriólogo ve la regla antes de registrar. El sistema no
                    trae reglas propias.
                </p>
                <p
                    v-if="!props.can.edit"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Las reglas las define el Director técnico responsable.
                </p>
            </div>
            <Button
                v-if="props.can.edit && editing === null"
                data-test="new-rule"
                @click="startNew"
                ><Plus class="size-4" /> Regla nueva</Button
            >
        </div>

        <form
            v-if="editing !== null"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="save"
        >
            <div class="grid gap-3 sm:grid-cols-[10rem_1fr_16rem]">
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
                    <Input v-model="form.name" />
                </label>
                <label class="grid gap-1 text-sm">
                    Qué hace
                    <select
                        v-model="form.effect"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option
                            v-for="e in props.effects"
                            :key="e.value"
                            :value="e.value"
                        >
                            {{ e.label }}
                        </option>
                    </select>
                </label>
            </div>
            <div class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                <label
                    v-if="form.effect === 'interpret'"
                    class="grid gap-1 text-sm"
                >
                    Interpretación
                    <select
                        v-model="form.interpretation"
                        class="h-9 rounded-md border bg-background px-2"
                    >
                        <option :value="null" disabled>Elegí…</option>
                        <option
                            v-for="i in props.interpretations"
                            :key="i.value"
                            :value="i.value"
                        >
                            {{ i.label }}
                        </option>
                    </select>
                </label>
                <label
                    class="grid gap-1 text-sm"
                    :class="{ 'sm:col-span-2': form.effect !== 'interpret' }"
                >
                    Comentario para el informe
                    {{ form.effect === 'comment' ? '' : '(opcional)' }}
                    <Input v-model="form.comment" />
                </label>
            </div>
            <InputError
                :message="
                    errors.code ??
                    errors.name ??
                    errors.effect ??
                    errors.interpretation ??
                    errors.comment ??
                    errors.lock_version
                "
            />

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Grupos de germen</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label
                        v-for="g in props.organismGroups"
                        :key="g.value"
                        class="flex items-center gap-1"
                    >
                        <input
                            v-model="form.organism_groups"
                            type="checkbox"
                            :value="g.value"
                            class="size-4"
                        />
                        {{ g.label }}
                    </label>
                </div>
                <InputError :message="errors.organism_groups" />
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">
                    Muestras (sin tildar = todas)
                </legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label
                        v-for="s in props.sampleTypes"
                        :key="s.id"
                        class="flex items-center gap-1"
                    >
                        <input
                            v-model="form.sample_type_ids"
                            type="checkbox"
                            :value="s.id"
                            class="size-4"
                        />
                        {{ s.name }}
                    </label>
                </div>
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="text-sm font-medium">Antibióticos</legend>
                <div class="grid gap-1 text-sm sm:grid-cols-3 lg:grid-cols-4">
                    <label
                        v-for="a in props.antibiotics"
                        :key="a.id"
                        class="flex items-center gap-1"
                    >
                        <input
                            v-model="form.antibiotic_ids"
                            type="checkbox"
                            :value="a.id"
                            class="size-4"
                        />
                        {{ a.name }}
                    </label>
                </div>
                <InputError :message="errors.antibiotic_ids" />
            </fieldset>

            <fieldset class="grid gap-2 sm:grid-cols-2">
                <legend class="text-sm font-medium">
                    Solo si una prueba del aislamiento dio (opcional)
                </legend>
                <select
                    v-model="form.identification_test_id"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    aria-label="Prueba"
                    @change="form.test_result_code = null"
                >
                    <option :value="null">Sin condición de prueba</option>
                    <option v-for="t in props.tests" :key="t.id" :value="t.id">
                        {{ t.name }}
                    </option>
                </select>
                <select
                    v-model="form.test_result_code"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    aria-label="Resultado"
                    :disabled="!selectedTest"
                >
                    <option :value="null" disabled>Resultado…</option>
                    <option
                        v-for="o in selectedTest?.options ?? []"
                        :key="o.code"
                        :value="o.code"
                    >
                        {{ o.label }}
                    </option>
                </select>
                <InputError
                    class="sm:col-span-2"
                    :message="errors.test_result_code"
                />
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="size-4"
                />
                Activa
            </label>

            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" @click="editing = null"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-rule"
                    >Guardar</Button
                >
            </div>
        </form>

        <p v-if="!props.rules.length" class="text-sm text-muted-foreground">
            No hay reglas cargadas.
        </p>
        <div class="grid gap-2 lg:grid-cols-2">
            <article
                v-for="rule in props.rules"
                :key="rule.id"
                class="grid gap-1 rounded-lg border p-3 text-sm"
                :class="{ 'opacity-60': !rule.is_active }"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="font-medium">
                        {{ rule.name }}
                        <span class="font-mono text-xs text-muted-foreground">{{
                            rule.code
                        }}</span>
                    </div>
                    <Button
                        v-if="props.can.edit && editing === null"
                        size="sm"
                        variant="ghost"
                        @click="startEdit(rule)"
                        >Editar</Button
                    >
                </div>
                <div class="flex flex-wrap gap-1">
                    <Badge>{{ rule.effect_label }}</Badge>
                    <Badge v-if="rule.interpretation" variant="outline">{{
                        rule.interpretation
                    }}</Badge>
                    <Badge v-if="!rule.is_active" variant="secondary"
                        >Inactiva</Badge
                    >
                </div>
                <p>
                    {{ rule.organism_groups.map(groupLabel).join(', ') }} ·
                    {{
                        rule.sample_type_ids.length
                            ? rule.sample_type_ids.map(sampleName).join(', ')
                            : 'Todas las muestras'
                    }}
                </p>
                <p class="text-muted-foreground">
                    {{ rule.antibiotic_ids.map(antibioticName).join(', ') }}
                </p>
                <p
                    v-if="rule.identification_test_id"
                    class="text-muted-foreground"
                >
                    Si {{ testName(rule.identification_test_id) }}:
                    {{
                        resultLabel(
                            rule.identification_test_id,
                            rule.test_result_code,
                        )
                    }}
                </p>
                <p v-if="rule.comment" class="text-xs">{{ rule.comment }}</p>
            </article>
        </div>
    </div>
</template>
