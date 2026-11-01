<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { statusClass } from '@/lib/configStatus';
import { index, show, store, template } from '@/routes/admin/breakpoint-sets';

type SetRow = {
    id: number;
    name: string;
    edition: string;
    status: string;
    status_label: string;
    is_synthetic: boolean;
    breakpoints_count: number;
};

const props = defineProps<{
    sets: SetRow[];
    can: { draft: boolean; approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Puntos de corte', href: index() }],
    },
});

const creating = ref(false);
const form = useForm({
    name: '',
    edition: '',
    notes: '',
    copy_from_id: null as number | null,
});

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Puntos de corte" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Puntos de corte
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Los puntos de corte CLSI tienen derechos de autor: el
                    sistema no los trae. El laboratorio carga los de su edición
                    del M100 (archivo CSV o tabla), el Director técnico
                    responsable los aprueba y los pone vigentes. Cada
                    antibiograma guarda el set con el que se interpretó.
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <a :href="template().url"
                        ><Download class="size-4" /> Plantilla CSV</a
                    >
                </Button>
                <Button
                    v-if="props.can.draft && !creating"
                    data-test="new-set"
                    @click="creating = true"
                    ><Plus class="size-4" /> Nuevo set</Button
                >
            </div>
        </div>

        <form
            v-if="creating"
            class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <label class="grid gap-1 text-sm">
                Nombre
                <Input
                    v-model="form.name"
                    required
                    placeholder="CLSI M100 — edición del laboratorio"
                />
                <InputError :message="form.errors.name" />
            </label>
            <label class="grid gap-1 text-sm">
                Edición
                <Input
                    v-model="form.edition"
                    required
                    placeholder="Ej.: M100 Ed. 3X (año)"
                />
                <InputError :message="form.errors.edition" />
            </label>
            <label class="grid gap-1 text-sm">
                Partir de
                <select
                    v-model="form.copy_from_id"
                    class="h-9 rounded-md border bg-background px-2"
                >
                    <option :value="null">Vacío</option>
                    <option
                        v-for="s in props.sets.filter((s) => !s.is_synthetic)"
                        :key="s.id"
                        :value="s.id"
                    >
                        Copia de {{ s.name }} ({{ s.edition }})
                    </option>
                </select>
                <InputError :message="form.errors.copy_from_id" />
            </label>
            <label class="grid gap-1 text-sm">
                Notas
                <Input v-model="form.notes" />
            </label>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <Button type="button" variant="ghost" @click="creating = false"
                    >Cancelar</Button
                >
                <Button type="submit" :disabled="form.processing"
                    >Crear borrador</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Set</th>
                        <th class="px-3 py-2 font-medium">Edición</th>
                        <th class="px-3 py-2 font-medium">Puntos de corte</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="set in props.sets"
                        :key="set.id"
                        class="border-t"
                        data-test="set-row"
                    >
                        <td class="px-3 py-2">
                            <Link :href="show(set.id)" class="underline">{{
                                set.name
                            }}</Link>
                            <Badge
                                v-if="set.is_synthetic"
                                variant="destructive"
                                class="ml-2"
                                >Ficticio · solo demo</Badge
                            >
                        </td>
                        <td class="px-3 py-2">{{ set.edition }}</td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ set.breakpoints_count }}
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="rounded border px-2 py-0.5 text-xs"
                                :class="statusClass(set.status)"
                                >{{ set.status_label }}</span
                            >
                        </td>
                    </tr>
                    <tr v-if="!props.sets.length">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No hay sets cargados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
