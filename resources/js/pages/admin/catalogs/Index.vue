<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, store } from '@/routes/admin/catalogs';

type Entry = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    organism_group: string | null;
    lock_version: number;
};

const props = defineProps<{
    catalog: {
        value: string;
        label: string;
        singular: string;
        has_organism_group: boolean;
    };
    types: { value: string; label: string; count: number }[];
    entries: Entry[];
    filters: { search: string | null };
    organismGroups: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catálogos', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(
        index(props.catalog.value).url,
        search.value ? { search: search.value } : {},
        { preserveState: true, preserveScroll: true },
    );
}

const newForm = useForm({
    code: '',
    name: '',
    is_active: true,
    organism_group: '',
});

function create() {
    newForm.post(store(props.catalog.value).url, {
        preserveScroll: true,
        onSuccess: () => newForm.reset(),
    });
}

const editing = ref<number | null>(null);
const editForm = useForm({
    id: 0,
    name: '',
    is_active: true,
    lock_version: 0,
});

function startEdit(entry: Entry) {
    editing.value = entry.id;
    editForm.defaults({
        id: entry.id,
        name: entry.name,
        is_active: entry.is_active,
        lock_version: entry.lock_version,
    });
    editForm.reset();
    editForm.clearErrors();
}

function saveEdit() {
    editForm.post(store(props.catalog.value).url, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}
</script>

<template>
    <Head :title="props.catalog.label" />

    <div class="grid gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Catálogos</h1>
            <p class="text-sm text-muted-foreground">
                El código no cambia después del alta (lo usan los protocolos y
                las importaciones). Nada se borra: lo que no se usa más se
                desactiva.
            </p>
        </div>

        <nav class="flex flex-wrap gap-1" aria-label="Catálogos">
            <Link
                v-for="type in props.types"
                :key="type.value"
                :href="index(type.value)"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="{
                    'bg-primary text-primary-foreground':
                        type.value === props.catalog.value,
                }"
                >{{ type.label }}
                <span class="text-xs opacity-70">({{ type.count }})</span></Link
            >
        </nav>

        <form
            class="grid gap-2 rounded-lg border p-4 sm:grid-cols-[8rem_1fr_auto_auto] sm:items-end"
            @submit.prevent="create"
        >
            <label class="grid gap-1 text-sm">
                Código
                <Input
                    v-model="newForm.code"
                    required
                    maxlength="20"
                    data-test="new-code"
                />
            </label>
            <label class="grid gap-1 text-sm">
                Nombre
                <Input v-model="newForm.name" required data-test="new-name" />
            </label>
            <label
                v-if="props.catalog.has_organism_group"
                class="grid gap-1 text-sm"
            >
                Grupo
                <select
                    v-model="newForm.organism_group"
                    class="h-9 rounded-md border bg-background px-2"
                    required
                >
                    <option value="" disabled>Elegí…</option>
                    <option
                        v-for="g in props.organismGroups"
                        :key="g.value"
                        :value="g.value"
                    >
                        {{ g.label }}
                    </option>
                </select>
            </label>
            <span v-else></span>
            <Button
                type="submit"
                :disabled="newForm.processing"
                data-test="add-entry"
                ><Plus class="size-4" /> Agregar
                {{ props.catalog.singular }}</Button
            >
            <div class="sm:col-span-4">
                <InputError
                    :message="
                        newForm.errors.code ??
                        newForm.errors.name ??
                        newForm.errors.organism_group
                    "
                />
                <p
                    v-if="props.catalog.has_organism_group"
                    class="text-xs text-muted-foreground"
                >
                    El grupo define el panel de antibiograma y los puntos de
                    corte que se aplican: se elige en el alta y no se cambia.
                </p>
            </div>
        </form>

        <form class="flex gap-2" @submit.prevent="applySearch">
            <Input
                v-model="search"
                class="max-w-sm"
                placeholder="Buscar por código o nombre"
            />
            <Button type="submit" variant="outline">Buscar</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Código</th>
                        <th class="px-3 py-2 font-medium">Nombre</th>
                        <th
                            v-if="props.catalog.has_organism_group"
                            class="px-3 py-2 font-medium"
                        >
                            Grupo
                        </th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in props.entries"
                        :key="entry.id"
                        class="border-t"
                        :class="{ 'text-muted-foreground': !entry.is_active }"
                    >
                        <td class="px-3 py-2 font-mono">{{ entry.code }}</td>
                        <template v-if="editing === entry.id">
                            <td class="px-3 py-2">
                                <Input v-model="editForm.name" />
                                <InputError
                                    :message="
                                        editForm.errors.name ??
                                        (
                                            editForm.errors as Record<
                                                string,
                                                string
                                            >
                                        ).lock_version
                                    "
                                />
                            </td>
                            <td
                                v-if="props.catalog.has_organism_group"
                                class="px-3 py-2"
                            >
                                {{ entry.organism_group }}
                            </td>
                            <td class="px-3 py-2">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="editForm.is_active"
                                        type="checkbox"
                                        class="size-4"
                                    />
                                    Activo
                                </label>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <Button
                                    size="sm"
                                    :disabled="editForm.processing"
                                    @click="saveEdit"
                                    >Guardar</Button
                                >
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    @click="editing = null"
                                    >Cancelar</Button
                                >
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-3 py-2">{{ entry.name }}</td>
                            <td
                                v-if="props.catalog.has_organism_group"
                                class="px-3 py-2"
                            >
                                {{ entry.organism_group }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        entry.is_active
                                            ? 'outline'
                                            : 'secondary'
                                    "
                                    >{{
                                        entry.is_active ? 'Activo' : 'Inactivo'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    @click="startEdit(entry)"
                                    >Editar</Button
                                >
                            </td>
                        </template>
                    </tr>
                    <tr v-if="!props.entries.length">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin ítems.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
