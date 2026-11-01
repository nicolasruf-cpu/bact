<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ScanLine, UserCheck } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import ReferenceTemplateNotice from '@/components/domain/ReferenceTemplateNotice.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatTime } from '@/lib/datetime';
import { uuid } from '@/lib/uuid';
import { create as receptionCreate, store } from '@/routes/reception';

type Option = { id: number; code: string; name: string };
type Choice = { value: string; label: string };
type PatientMatch = {
    document_number: string;
    last_name: string;
    first_name: string;
    birth_date: string | null;
    sex: string;
};
type Received = {
    ulid: string;
    barcode: string;
    patient: string;
    sample_type: string;
    studies: string[];
    priority: string;
    received_at: string;
    status: string;
};

const props = defineProps<{
    sampleTypes: Option[];
    studies: Option[];
    available: Record<number, number[]>;
    referenceTemplates: number[];
    priorities: Choice[];
    sexes: Choice[];
    receivedToday: Received[];
    patientMatch?: PatientMatch | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepción', href: receptionCreate() }],
    },
});

const emptyForm = () => ({
    idempotency_key: uuid(),
    barcode: '',
    document_number: '',
    last_name: '',
    first_name: '',
    birth_date: '',
    sex: 'U',
    sample_type_id:
        props.sampleTypes.length === 1
            ? props.sampleTypes[0].id
            : (null as number | null),
    study_ids: [] as number[],
    priority: 'routine',
    collected_at: '',
    requesting_service: '',
    requesting_physician: '',
    notes: '',
});

const form = useForm(emptyForm());
const knownPatient = ref(false);
const lookingUp = ref(false);

const studiesForType = computed(() => {
    const allowed = form.sample_type_id
        ? (props.available[form.sample_type_id] ?? [])
        : [];

    return props.studies.filter((study) => allowed.includes(study.id));
});

const usesReferenceTemplate = computed(
    () =>
        form.sample_type_id !== null &&
        props.referenceTemplates.includes(form.sample_type_id),
);

// Al cambiar el tipo de muestra se descartan estudios que no aplican; si hay uno solo, se marca.
watch(
    () => form.sample_type_id,
    () => {
        const allowed = studiesForType.value.map((s) => s.id);
        form.study_ids = form.study_ids.filter((id) => allowed.includes(id));
        if (form.study_ids.length === 0 && allowed.length === 1) {
            form.study_ids = [allowed[0]];
        }
    },
    { immediate: true },
);

function focusField(id: string) {
    void nextTick(() => document.getElementById(id)?.focus());
}

function lookupPatient() {
    const documentNumber = form.document_number.trim();
    if (!documentNumber) {
        knownPatient.value = false;

        return;
    }

    lookingUp.value = true;
    router.reload({
        only: ['patientMatch'],
        data: { document: documentNumber },
        onFinish: () => (lookingUp.value = false),
    });
}

watch(
    () => props.patientMatch,
    (match) => {
        if (
            match &&
            match.document_number === form.document_number.trim().toUpperCase()
        ) {
            knownPatient.value = true;
            form.last_name = match.last_name;
            form.first_name = match.first_name;
            form.birth_date = match.birth_date ?? '';
            form.sex = match.sex;
        } else {
            knownPatient.value = false;
        }
    },
);

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            const keep = {
                sample_type_id: form.sample_type_id,
                priority: 'routine',
            };
            form.defaults({ ...emptyForm(), ...keep });
            form.reset();
            knownPatient.value = false;
            focusField('barcode');
        },
    });
}
</script>

<template>
    <Head title="Recepción" />

    <div class="grid gap-6 p-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ScanLine class="size-5" /> Recepción de muestras
                </CardTitle>
                <CardDescription>
                    Escaneá la etiqueta del LIS y completá los datos de la
                    solicitud.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="barcode">Código de muestra</Label>
                        <Input
                            id="barcode"
                            v-model="form.barcode"
                            v-focus
                            autocomplete="off"
                            class="font-mono text-lg uppercase"
                            placeholder="Escaneá la etiqueta"
                            @keydown.enter.prevent="
                                focusField('document_number')
                            "
                        />
                        <InputError :message="form.errors.barcode" />
                    </div>

                    <fieldset class="grid gap-4 rounded-lg border p-4">
                        <legend class="px-1 text-sm font-medium">
                            Paciente
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="document_number">Documento</Label>
                                <Input
                                    id="document_number"
                                    v-model="form.document_number"
                                    autocomplete="off"
                                    @blur="lookupPatient"
                                    @keydown.enter.prevent="lookupPatient"
                                />
                                <InputError
                                    :message="form.errors.document_number"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="last_name">Apellido</Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    :readonly="knownPatient"
                                />
                                <InputError :message="form.errors.last_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="first_name">Nombre</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    :readonly="knownPatient"
                                />
                                <InputError :message="form.errors.first_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="birth_date"
                                    >Fecha de nacimiento</Label
                                >
                                <Input
                                    id="birth_date"
                                    v-model="form.birth_date"
                                    type="date"
                                    :readonly="knownPatient"
                                />
                                <InputError :message="form.errors.birth_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="sex">Sexo</Label>
                                <select
                                    id="sex"
                                    v-model="form.sex"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                    :disabled="knownPatient"
                                >
                                    <option
                                        v-for="sex in props.sexes"
                                        :key="sex.value"
                                        :value="sex.value"
                                    >
                                        {{ sex.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.sex" />
                            </div>
                            <div class="flex items-end">
                                <Badge
                                    v-if="lookingUp"
                                    variant="outline"
                                    class="gap-1"
                                    ><Spinner class="size-3" /> Buscando…</Badge
                                >
                                <Badge
                                    v-else-if="knownPatient"
                                    variant="secondary"
                                    class="gap-1"
                                    data-test="known-patient"
                                    ><UserCheck class="size-3" /> Paciente ya
                                    registrado</Badge
                                >
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 rounded-lg border p-4">
                        <legend class="px-1 text-sm font-medium">
                            Muestra y estudios
                        </legend>

                        <div class="grid gap-2">
                            <span class="text-sm font-medium"
                                >Tipo de muestra</span
                            >
                            <div class="flex flex-wrap gap-2" role="radiogroup">
                                <label
                                    v-for="type in props.sampleTypes"
                                    :key="type.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                >
                                    <input
                                        v-model="form.sample_type_id"
                                        type="radio"
                                        name="sample_type_id"
                                        :value="type.id"
                                    />
                                    {{ type.name }}
                                </label>
                            </div>
                            <InputError :message="form.errors.sample_type_id" />
                        </div>

                        <div class="grid gap-2">
                            <span class="text-sm font-medium">Estudios</span>
                            <p
                                v-if="
                                    form.sample_type_id &&
                                    studiesForType.length === 0
                                "
                                class="text-sm text-destructive"
                            >
                                No hay estudios con protocolo vigente para este
                                tipo de muestra.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="study in studiesForType"
                                    :key="study.id"
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                >
                                    <input
                                        v-model="form.study_ids"
                                        type="checkbox"
                                        :value="study.id"
                                    />
                                    {{ study.name }}
                                </label>
                            </div>
                            <InputError :message="form.errors.study_ids" />
                        </div>

                        <ReferenceTemplateNotice v-if="usesReferenceTemplate" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <span class="text-sm font-medium"
                                    >Prioridad</span
                                >
                                <div class="flex gap-2" role="radiogroup">
                                    <label
                                        v-for="priority in props.priorities"
                                        :key="priority.value"
                                        class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                    >
                                        <input
                                            v-model="form.priority"
                                            type="radio"
                                            name="priority"
                                            :value="priority.value"
                                        />
                                        {{ priority.label }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.priority" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="collected_at"
                                    >Fecha y hora de toma</Label
                                >
                                <Input
                                    id="collected_at"
                                    v-model="form.collected_at"
                                    type="datetime-local"
                                />
                                <InputError
                                    :message="form.errors.collected_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="requesting_service"
                                    >Servicio / procedencia</Label
                                >
                                <Input
                                    id="requesting_service"
                                    v-model="form.requesting_service"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="requesting_physician"
                                    >Médico solicitante</Label
                                >
                                <Input
                                    id="requesting_physician"
                                    v-model="form.requesting_physician"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="notes"
                                >Observaciones de recepción</Label
                            >
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="2"
                                class="rounded-md border bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </fieldset>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="form.processing"
                            data-test="receive-button"
                        >
                            <Spinner v-if="form.processing" />
                            Registrar recepción
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Recibidas hoy</CardTitle>
                <CardDescription
                    >{{ props.receivedToday.length }} muestras</CardDescription
                >
            </CardHeader>
            <CardContent class="grid gap-2">
                <p
                    v-if="props.receivedToday.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Todavía no se recibieron muestras hoy.
                </p>
                <div
                    v-for="sample in props.receivedToday"
                    :key="sample.ulid"
                    class="grid gap-1 rounded-md border p-2 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-medium">{{
                            sample.barcode
                        }}</span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ formatTime(sample.received_at) }}</span
                        >
                    </div>
                    <div class="truncate">{{ sample.patient }}</div>
                    <div
                        class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                    >
                        {{ sample.sample_type }} ·
                        {{ sample.studies.join(', ') }}
                        <PriorityBadge
                            v-if="sample.priority !== 'routine'"
                            :priority="sample.priority"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
