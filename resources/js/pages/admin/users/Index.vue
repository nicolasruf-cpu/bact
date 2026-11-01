<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BadgeCheck, Plus, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update as updateApprover } from '@/routes/admin/clinical-approver';
import { create, edit, index } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    is_clinical_approver: boolean;
    assignments: { site: string; role: string }[];
};

const props = defineProps<{
    users: UserRow[];
    filters: { search: string | null };
    approverCandidates: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(index().url, search.value ? { search: search.value } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

const approver = computed(() =>
    props.users.find((u) => u.is_clinical_approver),
);

const approverForm = useForm({
    user_id: approver.value?.id ?? (null as number | null),
    reason: '',
});

function saveApprover() {
    approverForm.post(updateApprover().url, {
        preserveScroll: true,
        onSuccess: () => approverForm.reset('reason'),
    });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Usuarios</h1>
                <p class="text-sm text-muted-foreground">
                    Altas, perfiles por sede y bajas. Nadie se borra: un usuario
                    dado de baja no ingresa, pero su nombre sigue en lo que
                    firmó.
                </p>
            </div>
            <Button as-child>
                <Link :href="create()" data-test="new-user"
                    ><Plus class="size-4" /> Nuevo usuario</Link
                >
            </Button>
        </div>

        <section class="grid gap-3 rounded-lg border p-4">
            <h2 class="flex items-center gap-2 font-medium">
                <BadgeCheck class="size-4" /> Responsable de la configuración
                clínica
            </h2>
            <p class="text-sm text-muted-foreground">
                Un único Director técnico aprueba y pone vigentes los protocolos
                y los puntos de corte de las tres sedes. Hoy:
                <strong class="text-foreground">{{
                    approver?.name ?? 'nadie designado'
                }}</strong
                >.
            </p>
            <form
                class="flex flex-wrap items-end gap-2"
                @submit.prevent="saveApprover"
            >
                <label class="grid gap-1 text-sm">
                    Director técnico
                    <select
                        v-model="approverForm.user_id"
                        class="h-9 rounded-md border bg-background px-2"
                        data-test="approver-select"
                    >
                        <option :value="null">Nadie</option>
                        <option
                            v-for="candidate in props.approverCandidates"
                            :key="candidate.id"
                            :value="candidate.id"
                        >
                            {{ candidate.name }}
                        </option>
                    </select>
                </label>
                <label class="grid flex-1 gap-1 text-sm">
                    Motivo
                    <Input
                        v-model="approverForm.reason"
                        placeholder="Ej.: designación por la dirección del laboratorio"
                    />
                </label>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="
                        approverForm.processing ||
                        !approverForm.reason ||
                        approverForm.user_id === (approver?.id ?? null)
                    "
                    >Guardar designación</Button
                >
            </form>
            <InputError
                :message="
                    approverForm.errors.user_id ?? approverForm.errors.reason
                "
            />
        </section>

        <form class="flex gap-2" @submit.prevent="applySearch">
            <Input
                v-model="search"
                class="max-w-sm"
                placeholder="Buscar por nombre o email"
            />
            <Button type="submit" variant="outline">Buscar</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Nombre</th>
                        <th class="px-3 py-2 font-medium">Email</th>
                        <th class="px-3 py-2 font-medium">Perfiles</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in props.users"
                        :key="user.id"
                        class="border-t align-top"
                        :class="{ 'text-muted-foreground': !user.is_active }"
                        data-test="user-row"
                    >
                        <td class="px-3 py-2">
                            <span class="flex items-center gap-2">
                                <UserRound class="size-4 shrink-0" />
                                {{ user.name }}
                                <Badge
                                    v-if="user.is_clinical_approver"
                                    variant="secondary"
                                    >Responsable</Badge
                                >
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ user.email }}</td>
                        <td class="px-3 py-2">
                            <span
                                v-for="(a, i) in user.assignments"
                                :key="i"
                                class="mr-1 mb-1 inline-block rounded border px-1.5 py-0.5 text-xs"
                                >{{ a.site }} · {{ a.role }}</span
                            >
                            <span v-if="!user.assignments.length">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="
                                    user.is_active ? 'outline' : 'destructive'
                                "
                                >{{
                                    user.is_active ? 'Activo' : 'Dado de baja'
                                }}</Badge
                            >
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="edit(user.id)"
                                class="text-sm underline"
                                >Editar</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!props.users.length">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No hay usuarios.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
