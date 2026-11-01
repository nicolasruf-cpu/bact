<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, ScanLine, Trash2, UserCheck } from '@lucide/vue';
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
type PatientSummary = {
    document_number: string;
    last_name: string;
    first_name: string;
    birth_date: string | null;
    sex: string;
};
type SampleSummary = {
    ulid: string;
    barcode: string;
    sample_type: string;
    studies: string[];
    received_at: string;
    status: string;
};
type OrderMatch = {
    order_number: string;
    patient: PatientSummary;
    requesting_service: string | null;
    requesting_physician: string | null;
    priority: string;
    samples: SampleSummary[];
};
type Delivery = {
    ulid: string;
    order_number: string | null;
    patient: string;
    priority: string;
    received_at: string;
    samples: SampleSummary[];
};
type SampleRow = {
    barcode: string;
    sample_type_id: number | null;
    study_ids: number[];
    collected_at: string;
    notes: string;
};

const props = defineProps<{
    sampleTypes: Option[];
    studies: Option[];
    available: Record<number, number[]>;
    referenceTemplates: number[];
    priorities: Choice[];
    sexes: Choice[];
    receivedToday: Delivery[];
    orderMatch?: OrderMatch | null;
    patientMatch?: PatientSummary | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepción', href: receptionCreate() }],
    },
});

const emptyForm = () => ({
    idempotency_key: uuid(),
    order_number: '',
    document_number: '',
    last_name: '',
    first_name: '',
    birth_date: '',
    sex: 'U',
    priority: 'routine',
    requesting_service: '',
    requesting_physician: '',
    samples: [] as SampleRow[],
});

const form = useForm(emptyForm());
const scan = ref('');
const lastSampleType = ref<number | null>(
    props.sampleTypes.length === 1 ? props.sampleTypes[0].id : null,
);
const existingOrder = ref<OrderMatch | null>(null);
const knownPatient = ref(false);
const lookingUp = ref(false);

const patientLocked = computed(
    () => knownPatient.value || existingOrder.value !== null,
);

function studiesFor(sampleTypeId: number | null): Option[] {
    const allowed = sampleTypeId ? (props.available[sampleTypeId] ?? []) : [];

    return props.studies.filter((study) => allowed.includes(study.id));
}

function defaultStudies(sampleTypeId: number | null): number[] {
    const options = studiesFor(sampleTypeId);

    return options.length === 1 ? [options[0].id] : [];
}

const usesReferenceTemplate = computed(() =>
    form.samples.some(
        (s) =>
            s.sample_type_id !== null &&
            props.referenceTemplates.includes(s.sample_type_id),
    ),
);

function focusField(id: string) {
    void nextTick(() => document.getElementById(id)?.focus());
}

// --- Orden -----------------------------------------------------------------

function lookupOrder() {
    const orderNumber = form.order_number.trim();
    if (!orderNumber) {
        existingOrder.value = null;

        return;
    }

    lookingUp.value = true;
    router.reload({
        only: ['orderMatch'],
        data: { order: orderNumber },
        onFinish: () => (lookingUp.value = false),
    });
}

watch(
    () => props.orderMatch,
    (match) => {
        if (
            match &&
            match.order_number === form.order_number.trim().toUpperCase()
        ) {
            existingOrder.value = match;
            form.document_number = match.patient.document_number;
            form.last_name = match.patient.last_name;
            form.first_name = match.patient.first_name;
            form.birth_date = match.patient.birth_date ?? '';
            form.sex = match.patient.sex;
            form.priority = match.priority;
            form.requesting_service = match.requesting_service ?? '';
            form.requesting_physician = match.requesting_physician ?? '';
            focusField('scan');
        } else {
            existingOrder.value = null;
            focusField('document_number');
        }
    },
);

// --- Paciente --------------------------------------------------------------

function lookupPatient() {
    const documentNumber = form.document_number.trim();
    if (!documentNumber || existingOrder.value) {
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
        if (existingOrder.value) {
            return;
        }
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

// --- Muestras --------------------------------------------------------------

function addSample() {
    const barcode = scan.value.trim().toUpperCase();
    if (!barcode) {
        return;
    }
    scan.value = '';
    if (form.samples.some((s) => s.barcode === barcode)) {
        return;
    }

    form.samples.push({
        barcode,
        sample_type_id: lastSampleType.value,
        study_ids: defaultStudies(lastSampleType.value),
        collected_at: '',
        notes: '',
    });
}

function setSampleType(row: SampleRow, sampleTypeId: number) {
    row.sample_type_id = sampleTypeId;
    lastSampleType.value = sampleTypeId;
    const allowed = studiesFor(sampleTypeId).map((s) => s.id);
    row.study_ids = row.study_ids.filter((id) => allowed.includes(id));
    if (row.study_ids.length === 0) {
        row.study_ids = defaultStudies(sampleTypeId);
    }
}

function removeSample(index: number) {
    form.samples.splice(index, 1);
}

function sampleError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`samples.${index}.${field}`];
}

// --- Envío -----------------------------------------------------------------

function resetAll() {
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
    existingOrder.value = null;
    knownPatient.value = false;
    focusField('order_number');
}

function submit() {
    addSample();
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: resetAll,
    });
}
</script>

<template>
    <Head title="Recepción" />

    <div class="grid gap-6 p-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <form class="grid content-start gap-6" @submit.prevent="submit">
            <!-- Orden y paciente -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ClipboardList class="size-5" /> Orden
                    </CardTitle>
                    <CardDescription>
                        Ingresá el número de orden de LABWIN. Si la orden ya
                        existe, las muestras se suman a ella.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div
                        class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                    >
                        <div class="grid gap-2">
                            <Label for="order_number">Número de orden</Label>
                            <Input
                                id="order_number"
                                v-model="form.order_number"
                                v-focus
                                autocomplete="off"
                                class="font-mono uppercase"
                                placeholder="Nº de orden (LABWIN)"
                                :readonly="existingOrder !== null"
                                @blur="lookupOrder"
                                @keydown.enter.prevent="lookupOrder"
                            />
                            <InputError :message="form.errors.order_number" />
                        </div>
                        <div class="flex items-center gap-2 pb-1">
                            <Badge
                                v-if="lookingUp"
                                variant="outline"
                                class="gap-1"
                                ><Spinner class="size-3" /> Buscando…</Badge
                            >
                            <Badge
                                v-else-if="existingOrder"
                                variant="secondary"
                                data-test="existing-order"
                                >Orden existente ·
                                {{ existingOrder.samples.length }}
                                recibidas</Badge
                            >
                            <Button
                                v-if="existingOrder"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="resetAll"
                                >Otra orden</Button
                            >
                        </div>
                    </div>

                    <div
                        v-if="existingOrder && existingOrder.samples.length"
                        class="rounded-md border bg-muted/40 p-3 text-sm"
                    >
                        <p class="mb-1 font-medium">
                            Ya recibidas en esta orden
                        </p>
                        <ul class="grid gap-1">
                            <li
                                v-for="sample in existingOrder.samples"
                                :key="sample.ulid"
                                class="flex flex-wrap gap-2 text-muted-foreground"
                            >
                                <span class="font-mono text-foreground">{{
                                    sample.barcode
                                }}</span>
                                {{ sample.sample_type }} ·
                                {{ sample.studies.join(', ') }} ·
                                {{ sample.status }}
                            </li>
                        </ul>
                    </div>

                    <fieldset class="grid gap-4 rounded-lg border p-4">
                        <legend
                            class="flex items-center gap-2 px-1 text-sm font-medium"
                        >
                            Paciente
                            <Badge
                                v-if="knownPatient && !existingOrder"
                                variant="secondary"
                                class="gap-1"
                                data-test="known-patient"
                                ><UserCheck class="size-3" /> Ya
                                registrado</Badge
                            >
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="document_number">Documento</Label>
                                <Input
                                    id="document_number"
                                    v-model="form.document_number"
                                    autocomplete="off"
                                    :readonly="existingOrder !== null"
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
                                    :readonly="patientLocked"
                                />
                                <InputError :message="form.errors.last_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="first_name">Nombre</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    :readonly="patientLocked"
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
                                    :readonly="patientLocked"
                                />
                                <InputError :message="form.errors.birth_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="sex">Sexo</Label>
                                <select
                                    id="sex"
                                    v-model="form.sex"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                    :disabled="patientLocked"
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
                        </div>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="requesting_physician"
                                >Médico solicitante</Label
                            >
                            <Input
                                id="requesting_physician"
                                v-model="form.requesting_physician"
                                :readonly="existingOrder !== null"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="requesting_service"
                                >Servicio / procedencia</Label
                            >
                            <Input
                                id="requesting_service"
                                v-model="form.requesting_service"
                                :readonly="existingOrder !== null"
                            />
                        </div>
                        <div class="grid gap-2">
                            <span class="text-sm font-medium">Prioridad</span>
                            <div class="flex flex-wrap gap-2" role="radiogroup">
                                <label
                                    v-for="priority in props.priorities"
                                    :key="priority.value"
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                                >
                                    <input
                                        v-model="form.priority"
                                        type="radio"
                                        name="priority"
                                        :value="priority.value"
                                        :disabled="existingOrder !== null"
                                    />
                                    {{ priority.label }}
                                </label>
                            </div>
                            <InputError :message="form.errors.priority" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Muestras -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ScanLine class="size-5" /> Muestras
                    </CardTitle>
                    <CardDescription>
                        Escaneá cada etiqueta: cada muestra tiene su propio
                        código.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="flex gap-2">
                        <Input
                            id="scan"
                            v-model="scan"
                            autocomplete="off"
                            class="font-mono text-lg uppercase"
                            placeholder="Escaneá o escribí el código y Enter"
                            @keydown.enter.prevent="addSample"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            @click="addSample"
                        >
                            <Plus /> Agregar
                        </Button>
                    </div>
                    <InputError :message="form.errors.samples" />

                    <p
                        v-if="form.samples.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Todavía no agregaste muestras.
                    </p>

                    <div
                        v-for="(row, index) in form.samples"
                        :key="row.barcode"
                        class="grid gap-3 rounded-lg border p-3"
                        data-test="sample-row"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-lg font-medium">{{
                                row.barcode
                            }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Quitar ${row.barcode}`"
                                @click="removeSample(index)"
                                ><Trash2
                            /></Button>
                        </div>
                        <InputError :message="sampleError(index, 'barcode')" />

                        <div
                            class="flex flex-wrap gap-2"
                            role="radiogroup"
                            :aria-label="`Tipo de muestra de ${row.barcode}`"
                        >
                            <label
                                v-for="type in props.sampleTypes"
                                :key="type.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                            >
                                <input
                                    type="radio"
                                    :name="`sample_type_${index}`"
                                    :value="type.id"
                                    :checked="row.sample_type_id === type.id"
                                    @change="setSampleType(row, type.id)"
                                />
                                {{ type.name }}
                            </label>
                        </div>
                        <InputError
                            :message="sampleError(index, 'sample_type_id')"
                        />

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-muted-foreground"
                                >Estudios:</span
                            >
                            <span
                                v-if="
                                    row.sample_type_id &&
                                    studiesFor(row.sample_type_id).length === 0
                                "
                                class="text-sm text-destructive"
                                >Sin estudios con protocolo vigente para este
                                tipo.</span
                            >
                            <label
                                v-for="study in studiesFor(row.sample_type_id)"
                                :key="study.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                            >
                                <input
                                    v-model="row.study_ids"
                                    type="checkbox"
                                    :value="study.id"
                                />
                                {{ study.name }}
                            </label>
                        </div>
                        <InputError
                            :message="sampleError(index, 'study_ids')"
                        />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label
                                    :for="`collected_at_${index}`"
                                    class="text-xs"
                                    >Fecha y hora de toma</Label
                                >
                                <Input
                                    :id="`collected_at_${index}`"
                                    v-model="row.collected_at"
                                    type="datetime-local"
                                />
                                <InputError
                                    :message="
                                        sampleError(index, 'collected_at')
                                    "
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`notes_${index}`" class="text-xs"
                                    >Observaciones</Label
                                >
                                <Input
                                    :id="`notes_${index}`"
                                    v-model="row.notes"
                                />
                            </div>
                        </div>
                    </div>

                    <ReferenceTemplateNotice v-if="usesReferenceTemplate" />

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="
                                form.processing || form.samples.length === 0
                            "
                            data-test="receive-button"
                        >
                            <Spinner v-if="form.processing" />
                            Registrar recepción
                            <template v-if="form.samples.length">
                                ({{ form.samples.length }}
                                {{
                                    form.samples.length === 1
                                        ? 'muestra'
                                        : 'muestras'
                                }})
                            </template>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>

        <!-- Recibidas hoy -->
        <Card class="h-fit">
            <CardHeader>
                <CardTitle class="text-base">Recibidas hoy</CardTitle>
                <CardDescription
                    >{{ props.receivedToday.length }} entregas</CardDescription
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
                    v-for="delivery in props.receivedToday"
                    :key="delivery.ulid"
                    class="grid gap-1 rounded-md border p-2 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">
                            Orden
                            <span class="font-mono">{{
                                delivery.order_number ?? 's/n'
                            }}</span>
                        </span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ formatTime(delivery.received_at) }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2 truncate">
                        {{ delivery.patient }}
                        <PriorityBadge
                            v-if="delivery.priority !== 'routine'"
                            :priority="delivery.priority"
                        />
                    </div>
                    <ul class="grid gap-0.5 text-xs text-muted-foreground">
                        <li
                            v-for="sample in delivery.samples"
                            :key="sample.ulid"
                        >
                            <span class="font-mono text-foreground">{{
                                sample.barcode
                            }}</span>
                            · {{ sample.sample_type }} ·
                            {{ sample.studies.join(', ') }}
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
