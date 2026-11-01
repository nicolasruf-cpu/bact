<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show as showVersion } from '@/routes/admin/protocol-versions';
import { index, store } from '@/routes/admin/protocols';
import { statusClass } from '@/lib/configStatus';

type Protocol = {
    id: number;
    name: string;
    study: string;
    study_code: string;
    sample_type: string;
    versions: {
        id: number;
        version: number;
        status: string;
        status_label: string;
        is_reference_template: boolean;
    }[];
};

const props = defineProps<{
    protocols: Protocol[];
    studies: { id: number; code: string; name: string }[];
    sampleTypes: { id: number; code: string; name: string }[];
    kinds: { value: string; label: string }[];
    can: { draft: boolean; approve: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Protocolos', href: index() }],
    },
});

const creating = ref(false);
const form = useForm({
    study_id: null as number | null,
    sample_type_id: null as number | null,
    name: '',
    processing_kind: 'plate',
});

function submit() {
    form.post(store().url);
}
</script>

<template>
    <Head title="Protocolos" />

    <div class="grid gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Protocolos
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Un protocolo por estudio y tipo de muestra. Cada cambio es
                    una versión nueva: se prepara en borrador, la aprueba el
                    Director técnico responsable y queda vigente para las
                    muestras que se reciban desde ese momento. Las muestras ya
                    recibidas siguen con la versión que tenían.
                </p>
            </div>
            <Button
                v-if="props.can.draft && !creating"
                data-test="new-protocol"
                @click="creating = true"
                ><Plus class="size-4" /> Nuevo protocolo</Button
            >
        </div>

        <p
            v-if="!props.can.approve"
            class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
        >
            Podés preparar borradores. Aprobarlos y ponerlos vigentes es del
            Director técnico responsable de la configuración clínica.
        </p>

        <form
            v-if="creating"
            class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >
            <label class="grid gap-1 text-sm">
                Estudio
                <select
                    v-model="form.study_id"
                    class="h-9 rounded-md border bg-background px-2"
                    required
                >
                    <option :value="null" disabled>Elegí…</option>
                    <option
                        v-for="s in props.studies"
                        :key="s.id"
                        :value="s.id"
                    >
                        {{ s.name }} ({{ s.code }})
                    </option>
                </select>
            </label>
            <label class="grid gap-1 text-sm">
                Tipo de muestra
                <select
                    v-model="form.sample_type_id"
                    class="h-9 rounded-md border bg-background px-2"
                    required
                >
                    <option :value="null" disabled>Elegí…</option>
                    <option
                        v-for="t in props.sampleTypes"
                        :key="t.id"
                        :value="t.id"
                    >
                        {{ t.name }} ({{ t.code }})
                    </option>
                </select>
            </label>
            <label class="grid gap-1 text-sm">
                Nombre (opcional)
                <Input
                    v-model="form.name"
                    placeholder="Estudio — tipo de muestra"
                />
            </label>
            <label class="grid gap-1 text-sm">
                Procesamiento
                <select
                    v-model="form.processing_kind"
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
            <div class="flex items-center justify-between gap-2 sm:col-span-2">
                <InputError
                    :message="
                        form.errors.study_id ?? form.errors.sample_type_id
                    "
                />
                <span class="flex gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="creating = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Crear borrador</Button
                    >
                </span>
            </div>
            <p class="text-xs text-muted-foreground sm:col-span-2">
                ¿Falta el estudio o el tipo de muestra? Se agregan en Catálogos.
            </p>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Estudio</th>
                        <th class="px-3 py-2 font-medium">Tipo de muestra</th>
                        <th class="px-3 py-2 font-medium">Versiones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="protocol in props.protocols"
                        :key="protocol.id"
                        class="border-t align-top"
                        data-test="protocol-row"
                    >
                        <td class="px-3 py-2">
                            {{ protocol.study }}
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ protocol.study_code }}</span
                            >
                        </td>
                        <td class="px-3 py-2">{{ protocol.sample_type }}</td>
                        <td class="px-3 py-2">
                            <span class="flex flex-wrap gap-1">
                                <Link
                                    v-for="v in protocol.versions"
                                    :key="v.id"
                                    :href="showVersion(v.id)"
                                    class="rounded border px-2 py-0.5 text-xs"
                                    :class="statusClass(v.status)"
                                    :title="
                                        v.is_reference_template
                                            ? 'Plantilla de referencia: requiere validación del laboratorio'
                                            : ''
                                    "
                                    >v{{ v.version }} · {{ v.status_label
                                    }}{{
                                        v.is_reference_template
                                            ? ' · plantilla'
                                            : ''
                                    }}</Link
                                >
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
