<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { active, index, store, update } from '@/routes/admin/users';

type Assignment = { site_id: number; role: string };

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        is_clinical_approver: boolean;
        lock_version: number;
        assignments: Assignment[];
    } | null;
    isSelf?: boolean;
    sites: { id: number; code: string; name: string; manageable: boolean }[];
    roles: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios', href: index() },
            { title: 'Usuario', href: '#' },
        ],
    },
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    assignments: (props.user?.assignments ?? []) as Assignment[],
    lock_version: props.user?.lock_version ?? null,
});

function has(siteId: number, role: string): boolean {
    return form.assignments.some(
        (a) => a.site_id === siteId && a.role === role,
    );
}

function toggle(siteId: number, role: string) {
    form.assignments = has(siteId, role)
        ? form.assignments.filter(
              (a) => !(a.site_id === siteId && a.role === role),
          )
        : [...form.assignments, { site_id: siteId, role }];
}

const manageableIds = computed(
    () => new Set(props.sites.filter((s) => s.manageable).map((s) => s.id)),
);

function submit() {
    form.transform((data) => ({
        ...data,
        // Solo viajan los perfiles de las sedes que este Administrador maneja.
        assignments: data.assignments.filter((a) =>
            manageableIds.value.has(a.site_id),
        ),
        password: data.password || null,
    }));

    if (props.user) {
        form.put(update(props.user.id).url, { preserveScroll: true });
    } else {
        form.post(store().url, { preserveScroll: true });
    }
}

const activeForm = useForm({
    active: !(props.user?.is_active ?? true),
    reason: '',
    lock_version: props.user?.lock_version ?? 0,
});

function toggleActive() {
    if (!props.user) return;
    activeForm.post(active(props.user.id).url, { preserveScroll: true });
}

const errorsFor = (key: string) => (form.errors as Record<string, string>)[key];
</script>

<template>
    <Head :title="props.user ? props.user.name : 'Nuevo usuario'" />

    <div class="grid max-w-4xl gap-4 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ props.user ? props.user.name : 'Nuevo usuario' }}
            </h1>
            <Badge
                v-if="props.user && !props.user.is_active"
                variant="destructive"
                >Dado de baja</Badge
            >
            <Badge v-if="props.user?.is_clinical_approver" variant="secondary"
                >Responsable de la configuración clínica</Badge
            >
        </div>

        <form class="grid gap-5 rounded-lg border p-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1">
                    <Label for="name">Nombre y apellido</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1">
                    <Label for="email">Email (usuario de ingreso)</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1">
                    <Label for="password">{{
                        props.user ? 'Nueva contraseña' : 'Contraseña inicial'
                    }}</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        :required="!props.user"
                        :placeholder="
                            props.user ? 'Dejala vacía para no cambiarla' : ''
                        "
                    />
                    <InputError :message="form.errors.password" />
                    <p class="text-xs text-muted-foreground">
                        Comunicásela a la persona en forma privada; puede
                        cambiarla en su configuración.
                    </p>
                </div>
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    Perfiles por sede
                </legend>
                <p v-if="props.isSelf" class="text-sm text-muted-foreground">
                    Tus propios perfiles los cambia otro Administrador.
                </p>
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">Sede</th>
                                <th
                                    v-for="role in props.roles"
                                    :key="role.value"
                                    class="px-3 py-2 text-center font-medium"
                                >
                                    {{ role.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="site in props.sites"
                                :key="site.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    {{ site.name }}
                                    <span
                                        v-if="!site.manageable"
                                        class="block text-xs text-muted-foreground"
                                        >No administrás usuarios en esta
                                        sede</span
                                    >
                                </td>
                                <td
                                    v-for="role in props.roles"
                                    :key="role.value"
                                    class="px-3 py-2 text-center"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="has(site.id, role.value)"
                                        :disabled="
                                            !site.manageable || props.isSelf
                                        "
                                        :aria-label="`${role.label} en ${site.name}`"
                                        :data-test="`role-${site.code}-${role.value}`"
                                        @change="toggle(site.id, role.value)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError :message="errorsFor('assignments')" />
            </fieldset>

            <InputError :message="errorsFor('lock_version')" />

            <div class="flex justify-end">
                <Button
                    type="submit"
                    size="lg"
                    :disabled="form.processing"
                    data-test="save-user"
                >
                    <Spinner v-if="form.processing" />
                    {{ props.user ? 'Guardar cambios' : 'Crear usuario' }}
                </Button>
            </div>
        </form>

        <form
            v-if="props.user && !props.isSelf"
            class="grid gap-2 rounded-lg border p-4"
            @submit.prevent="toggleActive"
        >
            <h2 class="font-medium">
                {{ props.user.is_active ? 'Dar de baja' : 'Reactivar' }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    props.user.is_active
                        ? 'La persona deja de ingresar y se cierran sus sesiones abiertas. Si es responsable de la configuración clínica, pierde la designación.'
                        : 'La persona vuelve a ingresar con sus perfiles.'
                }}
            </p>
            <div class="flex flex-wrap items-end gap-2">
                <label class="grid flex-1 gap-1 text-sm">
                    Motivo
                    <Input v-model="activeForm.reason" required />
                </label>
                <Button
                    type="submit"
                    :variant="props.user.is_active ? 'destructive' : 'outline'"
                    :disabled="activeForm.processing || !activeForm.reason"
                    data-test="toggle-active"
                    >{{
                        props.user.is_active ? 'Dar de baja' : 'Reactivar'
                    }}</Button
                >
            </div>
            <InputError
                :message="
                    activeForm.errors.reason ?? activeForm.errors.lock_version
                "
            />
        </form>
    </div>
</template>
