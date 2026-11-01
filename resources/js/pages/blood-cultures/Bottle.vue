<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Droplets,
    FileText,
    FlaskConical,
    Microscope,
    Phone,
} from '@lucide/vue';
import { computed } from 'vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { formatMinutes } from '@/lib/elapsed';
import { uuid } from '@/lib/uuid';
import { read as readSubculture } from '@/routes/blind-subcultures';
import { load as loadRoute, show as showBottle } from '@/routes/blood-cultures';
import {
    gram as gramRoute,
    negative as negativeRoute,
    positive as positiveRoute,
    subculture as subcultureRoute,
} from '@/routes/bottles';
import { store as storeSubculture } from '@/routes/bottles/blind-subcultures';
import { store as storeCommunication } from '@/routes/critical-alerts/communications';
import { preliminary as preliminaryRoute } from '@/routes/reports';
import { create as validationRoute } from '@/routes/validations';
import type { SampleHeader } from '@/types/clinical';

type Option = { code: string; label: string };

type BlindSubculture = {
    ulid: string;
    number: number;
    medium: string;
    subcultured_at: string;
    readable_at: string;
    readable: boolean;
    result: string | null;
    result_label: string | null;
    read_at: string | null;
};

type Bottle = {
    ulid: string;
    method: string;
    method_label: string;
    bottle_type: string;
    instrument: string | null;
    instrument_position: string | null;
    loaded_at: string;
    incubation_ends_at: string;
    incubation_days: number;
    status: 'incubating' | 'positive' | 'negative';
    status_label: string;
    positive_at: string | null;
    positivity_source: string | null;
    minutes_to_positivity: number | null;
    notes: string | null;
    can_confirm_negative_now: boolean;
    next_subculture: {
        number: number;
        available_at: string;
        available: boolean;
    } | null;
    blind_subcultures: BlindSubculture[];
    gram: {
        option_code: string;
        label: string;
        description: string | null;
        performed_at: string;
    } | null;
};

type SummaryGroup = {
    study: string;
    total: number;
    positive: number;
    negative: number;
    incubating: number;
    pending: number;
    text: string;
    bottles: {
        sample_study_id: number;
        barcode: string;
        status: string;
        status_label: string;
        positive_at: string | null;
        minutes_to_positivity: number | null;
        gram: string | null;
    }[];
};

type CriticalAlert = {
    ulid: string;
    summary: string;
    status: string;
    status_label: string;
    opened_at: string;
    closed_at: string | null;
    communications: {
        recipient_name: string;
        recipient_role: string | null;
        channel: string;
        successful: boolean;
        read_back_confirmed: boolean;
        notes: string | null;
        communicated_by: string;
        communicated_at: string;
    }[];
};

const props = defineProps<{
    sample: SampleHeader;
    studyStatus: string;
    settings: {
        incubation_days_automated: number;
        incubation_days_manual: number;
        instruments: string[];
        bottle_types: Option[];
        gram_options: Option[];
    } | null;
    methods: { value: string; label: string }[];
    bottle: Bottle | null;
    culture: {
        ulid: string;
        status: string;
        status_label: string;
        inoculated_at: string;
        reading_url: string;
    } | null;
    orderSummary: SummaryGroup[];
    criticalAlerts: CriticalAlert[];
    reports: {
        ulid: string;
        version: number;
        type_label: string;
        status: string;
        status_label: string;
        validated_at: string;
        url: string;
    }[];
    channels: { value: string; label: string }[];
    can: Record<
        | 'load'
        | 'signal_positive'
        | 'confirm_negative'
        | 'subculture'
        | 'read_negative'
        | 'read_positive'
        | 'gram'
        | 'communicate'
        | 'preliminary',
        boolean
    >;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mesada de hemocultivos',
                href: '/workbenches/blood-cultures',
            },
            { title: 'Frasco', href: '#' },
        ],
    },
});

/** Valor para <input type="datetime-local"> en la hora local del navegador. */
function localInput(date: Date = new Date()): string {
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function toIso(local: string): string | null {
    return local ? new Date(local).toISOString() : null;
}

// --- Carga -------------------------------------------------------------------
const loadForm = useForm({
    method: 'automated',
    bottle_type: props.settings?.bottle_types[0]?.code ?? '',
    instrument: props.settings?.instruments[0] ?? '',
    instrument_position: '',
    loaded_at: localInput(),
});

function submitLoad() {
    loadForm
        .transform((data) => ({
            ...data,
            instrument: data.method === 'automated' ? data.instrument : null,
            instrument_position:
                data.method === 'automated' ? data.instrument_position : null,
            loaded_at: toIso(data.loaded_at),
        }))
        .post(loadRoute(props.sample.sample_study_id).url, {
            preserveScroll: true,
        });
}

const loadDays = computed(() =>
    loadForm.method === 'automated'
        ? props.settings?.incubation_days_automated
        : props.settings?.incubation_days_manual,
);

// --- Positivo / negativo -------------------------------------------------------
const positiveForm = useForm({ positive_at: localInput(), notes: '' });

function submitPositive() {
    if (!props.bottle) {
        return;
    }

    positiveForm
        .transform((data) => ({
            ...data,
            positive_at: toIso(data.positive_at),
        }))
        .post(positiveRoute(props.bottle.ulid).url, { preserveScroll: true });
}

const negativeForm = useForm({});

function submitNegative() {
    if (props.bottle) {
        negativeForm.post(negativeRoute(props.bottle.ulid).url, {
            preserveScroll: true,
        });
    }
}

// --- Repiques ------------------------------------------------------------------
const subcultureForm = useForm({});

function submitBlindSubculture() {
    if (props.bottle) {
        subcultureForm.post(storeSubculture(props.bottle.ulid).url, {
            preserveScroll: true,
        });
    }
}

const readForm = useForm({ result: '', notes: '' });

function readBlind(subculture: BlindSubculture, result: string) {
    readForm.result = result;
    readForm.post(readSubculture(subculture.ulid).url, {
        preserveScroll: true,
    });
}

// --- Gram y subcultivo -----------------------------------------------------------
const gramForm = useForm({ option_code: '', description: '' });

function submitGram() {
    if (props.bottle) {
        gramForm.post(gramRoute(props.bottle.ulid).url, {
            preserveScroll: true,
        });
    }
}

const workupForm = useForm({});

function submitWorkup() {
    if (props.bottle) {
        workupForm.post(subcultureRoute(props.bottle.ulid).url, {
            preserveScroll: true,
        });
    }
}

// --- Comunicación del crítico -------------------------------------------------------
const communicationForm = useForm({
    idempotency_key: uuid(),
    recipient_name: '',
    recipient_role: '',
    channel: props.channels[0]?.value ?? 'phone',
    successful: true,
    read_back_confirmed: false,
    notes: '',
});

function submitCommunication(alert: CriticalAlert) {
    communicationForm.post(storeCommunication(alert.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            communicationForm.reset();
            communicationForm.idempotency_key = uuid();
        },
    });
}

// --- Informes -----------------------------------------------------------------------
const canIssuePreliminary = computed(
    () =>
        props.can.preliminary &&
        props.bottle?.status === 'positive' &&
        ['in_process', 'preliminary_reported'].includes(props.studyStatus),
);

function issuePreliminary() {
    router.post(
        preliminaryRoute(props.sample.sample_study_id).url,
        {},
        { preserveScroll: true },
    );
}

const statusClass: Record<string, string> = {
    incubating: 'bg-blue-600 text-white hover:bg-blue-600',
    positive: 'bg-red-600 text-white hover:bg-red-600',
    negative: 'bg-green-700 text-white hover:bg-green-700',
    pending: '',
};
</script>

<template>
    <Head :title="`Frasco ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid content-start gap-4">
                <!-- Carga del frasco -->
                <form
                    v-if="!props.bottle"
                    class="grid gap-4 rounded-lg border p-4"
                    @submit.prevent="submitLoad"
                >
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        <Droplets class="size-5" /> Carga del frasco
                    </h1>
                    <p
                        v-if="!props.can.load"
                        class="text-sm text-muted-foreground"
                    >
                        No tenés permiso para cargar frascos.
                    </p>
                    <template v-else>
                        <fieldset class="flex flex-wrap gap-4">
                            <legend class="mb-1 text-sm font-medium">
                                Procesamiento
                            </legend>
                            <label
                                v-for="method in props.methods"
                                :key="method.value"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="loadForm.method"
                                    type="radio"
                                    :value="method.value"
                                    class="size-4"
                                />
                                {{ method.label }}
                            </label>
                        </fieldset>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label for="bottle_type">Tipo de frasco</Label>
                                <select
                                    id="bottle_type"
                                    v-model="loadForm.bottle_type"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                >
                                    <option
                                        v-for="type in props.settings
                                            ?.bottle_types ?? []"
                                        :key="type.code"
                                        :value="type.code"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="loadForm.errors.bottle_type"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="loaded_at">Hora de carga</Label>
                                <input
                                    id="loaded_at"
                                    v-model="loadForm.loaded_at"
                                    type="datetime-local"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                />
                                <InputError
                                    :message="loadForm.errors.loaded_at"
                                />
                            </div>
                            <template v-if="loadForm.method === 'automated'">
                                <div class="grid gap-1">
                                    <Label for="instrument">Equipo</Label>
                                    <select
                                        id="instrument"
                                        v-model="loadForm.instrument"
                                        class="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option
                                            v-for="instrument in props.settings
                                                ?.instruments ?? []"
                                            :key="instrument"
                                            :value="instrument"
                                        >
                                            {{ instrument }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="loadForm.errors.instrument"
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="instrument_position"
                                        >Posición en el equipo</Label
                                    >
                                    <Input
                                        id="instrument_position"
                                        v-model="loadForm.instrument_position"
                                        placeholder="p. ej., A-12"
                                    />
                                </div>
                            </template>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Incubación del protocolo:
                            <strong>{{ loadDays }} días</strong>
                            <template v-if="loadForm.method === 'manual'">
                                , con un repique por día.</template
                            >
                        </p>
                        <InputError
                            :message="
                                (loadForm.errors as Record<string, string>)
                                    .study
                            "
                        />
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                size="lg"
                                :disabled="loadForm.processing"
                                data-test="load-bottle"
                            >
                                <Spinner v-if="loadForm.processing" />
                                Registrar carga
                            </Button>
                        </div>
                    </template>
                </form>

                <!-- Frasco cargado -->
                <section
                    v-else
                    class="grid gap-4 rounded-lg border p-4"
                    data-test="bottle"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h1
                            class="flex items-center gap-2 text-xl font-semibold"
                        >
                            <Droplets class="size-5" /> Frasco
                            {{ props.bottle.bottle_type.toLowerCase() }}
                        </h1>
                        <Badge
                            :class="statusClass[props.bottle.status]"
                            data-test="bottle-status"
                            >{{ props.bottle.status_label }}</Badge
                        >
                    </div>
                    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="inline text-muted-foreground">
                                Procesamiento:
                            </dt>
                            <dd class="inline">
                                {{ props.bottle.method_label }}
                                <template v-if="props.bottle.instrument">
                                    · {{ props.bottle.instrument }}</template
                                >
                                <template
                                    v-if="props.bottle.instrument_position"
                                >
                                    · posición
                                    {{
                                        props.bottle.instrument_position
                                    }}</template
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="inline text-muted-foreground">
                                Cargado:
                            </dt>
                            <dd class="inline">
                                {{ formatDateTime(props.bottle.loaded_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="inline text-muted-foreground">
                                Incubación:
                            </dt>
                            <dd class="inline">
                                {{ props.bottle.incubation_days }} días (hasta
                                {{
                                    formatDateTime(
                                        props.bottle.incubation_ends_at,
                                    )
                                }})
                            </dd>
                        </div>
                        <div v-if="props.bottle.positive_at">
                            <dt class="inline text-muted-foreground">
                                Positivo:
                            </dt>
                            <dd class="inline font-medium">
                                {{ formatDateTime(props.bottle.positive_at) }}
                                · {{ props.bottle.positivity_source }}
                                <template
                                    v-if="
                                        props.bottle.minutes_to_positivity !==
                                        null
                                    "
                                >
                                    · a las
                                    {{
                                        formatMinutes(
                                            props.bottle.minutes_to_positivity,
                                        )
                                    }}
                                    de incubación</template
                                >
                            </dd>
                        </div>
                        <div v-if="props.bottle.notes" class="sm:col-span-2">
                            <dt class="inline text-muted-foreground">
                                Observaciones:
                            </dt>
                            <dd class="inline">{{ props.bottle.notes }}</dd>
                        </div>
                    </dl>

                    <!-- Registrar positivo -->
                    <form
                        v-if="
                            props.bottle.status === 'incubating' &&
                            props.can.signal_positive
                        "
                        class="grid gap-3 rounded-md border border-red-300 bg-red-50/50 p-3 dark:bg-red-950/20"
                        @submit.prevent="submitPositive"
                    >
                        <h2 class="font-medium">
                            {{
                                props.bottle.method === 'automated'
                                    ? 'Alarma de positivo del equipo'
                                    : 'Signos visibles de positividad'
                            }}
                        </h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label for="positive_at">{{
                                    props.bottle.method === 'automated'
                                        ? 'Hora que informa el equipo'
                                        : 'Hora'
                                }}</Label>
                                <input
                                    id="positive_at"
                                    v-model="positiveForm.positive_at"
                                    type="datetime-local"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                />
                                <InputError
                                    :message="positiveForm.errors.positive_at"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="positive_notes"
                                    >Observaciones</Label
                                >
                                <Input
                                    id="positive_notes"
                                    v-model="positiveForm.notes"
                                    :placeholder="
                                        props.bottle.method === 'automated'
                                            ? 'Opcional'
                                            : 'p. ej., turbidez, hemólisis, gas'
                                    "
                                />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="positiveForm.processing"
                                data-test="mark-positive"
                            >
                                <AlertTriangle class="size-4" /> Registrar
                                positivo
                            </Button>
                        </div>
                    </form>

                    <!-- Descarga del negativo (equipo) -->
                    <div
                        v-if="
                            props.bottle.status === 'incubating' &&
                            props.bottle.method === 'automated'
                        "
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                    >
                        <div class="text-sm">
                            <div class="font-medium">
                                Descarga como negativo
                            </div>
                            <div class="text-muted-foreground">
                                <template
                                    v-if="props.bottle.can_confirm_negative_now"
                                    >Se cumplieron los
                                    {{ props.bottle.incubation_days }} días de
                                    incubación sin alarma.</template
                                >
                                <template v-else
                                    >Disponible desde
                                    {{
                                        formatDateTime(
                                            props.bottle.incubation_ends_at,
                                        )
                                    }}.</template
                                >
                            </div>
                            <InputError
                                :message="
                                    (
                                        negativeForm.errors as Record<
                                            string,
                                            string
                                        >
                                    ).bottle
                                "
                            />
                        </div>
                        <Button
                            v-if="props.can.confirm_negative"
                            variant="outline"
                            :disabled="
                                negativeForm.processing ||
                                !props.bottle.can_confirm_negative_now
                            "
                            data-test="confirm-negative"
                            @click="submitNegative"
                        >
                            Cerrar como negativo
                        </Button>
                    </div>

                    <!-- Repiques (manual) -->
                    <div
                        v-if="props.bottle.method === 'manual'"
                        class="grid gap-2"
                    >
                        <h2 class="font-medium">Repiques</h2>
                        <table
                            v-if="props.bottle.blind_subcultures.length"
                            class="w-full text-sm"
                        >
                            <thead
                                class="text-left text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="py-1 font-medium">N.º</th>
                                    <th class="py-1 font-medium">Repicado</th>
                                    <th class="py-1 font-medium">Lectura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="s in props.bottle.blind_subcultures"
                                    :key="s.ulid"
                                    class="border-t"
                                >
                                    <td class="py-1.5">{{ s.number }}</td>
                                    <td class="py-1.5">
                                        {{ formatDateTime(s.subcultured_at) }}
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >· {{ s.medium }}</span
                                        >
                                    </td>
                                    <td class="py-1.5">
                                        <template v-if="s.result">
                                            <Badge
                                                :variant="
                                                    s.result === 'growth'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                "
                                                >{{ s.result_label }}</Badge
                                            >
                                        </template>
                                        <template
                                            v-else-if="
                                                props.bottle.status !==
                                                'incubating'
                                            "
                                            >—</template
                                        >
                                        <span
                                            v-else-if="!s.readable"
                                            class="text-muted-foreground"
                                            >Desde
                                            {{
                                                formatDateTime(s.readable_at)
                                            }}</span
                                        >
                                        <span v-else class="flex gap-2">
                                            <Button
                                                v-if="props.can.read_negative"
                                                size="sm"
                                                variant="outline"
                                                :disabled="readForm.processing"
                                                @click="
                                                    readBlind(s, 'no_growth')
                                                "
                                                >Sin desarrollo</Button
                                            >
                                            <Button
                                                v-if="props.can.read_positive"
                                                size="sm"
                                                variant="destructive"
                                                :disabled="readForm.processing"
                                                @click="readBlind(s, 'growth')"
                                                >Desarrollo</Button
                                            >
                                            <span
                                                v-if="
                                                    !props.can.read_negative &&
                                                    !props.can.read_positive
                                                "
                                                class="text-muted-foreground"
                                                >Pendiente de lectura</span
                                            >
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <InputError :message="readForm.errors.result" />
                        <div
                            v-if="props.bottle.next_subculture"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"
                        >
                            <span>
                                Repique n.º
                                {{ props.bottle.next_subculture.number }} de
                                {{ props.bottle.incubation_days }}
                                <span
                                    v-if="
                                        !props.bottle.next_subculture.available
                                    "
                                    class="text-muted-foreground"
                                >
                                    · desde
                                    {{
                                        formatDateTime(
                                            props.bottle.next_subculture
                                                .available_at,
                                        )
                                    }}</span
                                >
                            </span>
                            <Button
                                v-if="props.can.subculture"
                                :disabled="
                                    subcultureForm.processing ||
                                    !props.bottle.next_subculture.available
                                "
                                data-test="blind-subculture"
                                @click="submitBlindSubculture"
                                >Registrar repique</Button
                            >
                        </div>
                        <InputError
                            :message="
                                (
                                    subcultureForm.errors as Record<
                                        string,
                                        string
                                    >
                                ).bottle
                            "
                        />
                    </div>

                    <!-- Gram -->
                    <div
                        v-if="props.bottle.status === 'positive'"
                        class="grid gap-3"
                    >
                        <div
                            v-if="props.bottle.gram"
                            class="rounded-md border p-3 text-sm"
                            data-test="gram"
                        >
                            <div class="font-medium">
                                <Microscope class="mr-1 inline size-4" />
                                Gram: {{ props.bottle.gram.label }}
                            </div>
                            <div
                                v-if="props.bottle.gram.description"
                                class="text-muted-foreground"
                            >
                                {{ props.bottle.gram.description }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    formatDateTime(
                                        props.bottle.gram.performed_at,
                                    )
                                }}
                            </div>
                        </div>
                        <form
                            v-else-if="props.can.gram"
                            class="grid gap-3 rounded-md border border-red-300 p-3"
                            @submit.prevent="submitGram"
                        >
                            <h2 class="flex items-center gap-2 font-medium">
                                <Microscope class="size-4" /> Gram del frasco
                                positivo
                            </h2>
                            <div class="grid gap-1">
                                <Label for="gram_option">Resultado</Label>
                                <select
                                    id="gram_option"
                                    v-model="gramForm.option_code"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                    data-test="gram-option"
                                >
                                    <option value="" disabled>
                                        Elegí el resultado
                                    </option>
                                    <option
                                        v-for="option in props.settings
                                            ?.gram_options ?? []"
                                        :key="option.code"
                                        :value="option.code"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="gramForm.errors.option_code"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="gram_description"
                                    >Descripción</Label
                                >
                                <Input
                                    id="gram_description"
                                    v-model="gramForm.description"
                                    placeholder="Opcional"
                                />
                            </div>
                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="
                                        gramForm.processing ||
                                        !gramForm.option_code
                                    "
                                    data-test="save-gram"
                                    >Registrar Gram</Button
                                >
                            </div>
                        </form>
                        <p v-else class="text-sm text-muted-foreground">
                            Gram pendiente.
                        </p>
                    </div>

                    <!-- Subcultivo / cultivo del positivo -->
                    <div
                        v-if="props.bottle.status === 'positive'"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"
                    >
                        <template v-if="props.culture">
                            <div>
                                <div class="font-medium">
                                    <FlaskConical class="mr-1 inline size-4" />
                                    Cultivo: {{ props.culture.status_label }}
                                </div>
                                <div class="text-muted-foreground">
                                    Sembrado
                                    {{
                                        formatDateTime(
                                            props.culture.inoculated_at,
                                        )
                                    }}
                                    · sigue en las mesadas de lectura,
                                    identificación y antibiogramas.
                                </div>
                            </div>
                            <Link
                                v-if="
                                    props.can.read_negative ||
                                    props.can.read_positive
                                "
                                :href="props.culture.reading_url"
                                class="rounded-md border px-3 py-1.5 hover:border-primary"
                                >Ir a la lectura</Link
                            >
                        </template>
                        <template v-else>
                            <div>
                                <div class="font-medium">
                                    Subcultivo del frasco positivo
                                </div>
                                <div class="text-muted-foreground">
                                    En los medios del protocolo.
                                </div>
                            </div>
                            <Button
                                v-if="props.can.subculture"
                                :disabled="workupForm.processing"
                                data-test="subculture"
                                @click="submitWorkup"
                                >Registrar subcultivo</Button
                            >
                        </template>
                    </div>
                </section>

                <!-- Resultado crítico -->
                <section
                    v-for="alert in props.criticalAlerts"
                    :key="alert.ulid"
                    class="grid gap-3 rounded-lg border p-4"
                    :class="
                        alert.status === 'open'
                            ? 'border-red-500 bg-red-50/60 dark:bg-red-950/30'
                            : ''
                    "
                    data-test="critical-alert"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="flex items-center gap-2 font-semibold">
                            <AlertTriangle class="size-4" /> Resultado crítico
                        </h2>
                        <Badge
                            :variant="
                                alert.status === 'open'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                            >{{ alert.status_label }}</Badge
                        >
                    </div>
                    <p class="text-sm">{{ alert.summary }}</p>

                    <ul
                        v-if="alert.communications.length"
                        class="grid gap-1 text-sm"
                    >
                        <li
                            v-for="(c, index) in alert.communications"
                            :key="index"
                            class="rounded-md border bg-background p-2"
                        >
                            <span class="font-medium">{{
                                c.successful ? 'Comunicado' : 'Sin respuesta'
                            }}</span>
                            a {{ c.recipient_name }}
                            <template v-if="c.recipient_role">
                                ({{ c.recipient_role }})</template
                            >
                            · {{ c.channel }}
                            <template v-if="c.read_back_confirmed">
                                · relectura confirmada</template
                            >
                            <div class="text-xs text-muted-foreground">
                                {{ formatDateTime(c.communicated_at) }} ·
                                {{ c.communicated_by }}
                                <template v-if="c.notes">
                                    · {{ c.notes }}</template
                                >
                            </div>
                        </li>
                    </ul>

                    <form
                        v-if="alert.status === 'open' && props.can.communicate"
                        class="grid gap-3"
                        @submit.prevent="submitCommunication(alert)"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="grid gap-1">
                                <Label for="recipient_name"
                                    >A quién se comunicó</Label
                                >
                                <Input
                                    id="recipient_name"
                                    v-model="communicationForm.recipient_name"
                                    placeholder="Nombre"
                                    data-test="recipient-name"
                                />
                                <InputError
                                    :message="
                                        communicationForm.errors.recipient_name
                                    "
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="recipient_role"
                                    >Rol o servicio</Label
                                >
                                <Input
                                    id="recipient_role"
                                    v-model="communicationForm.recipient_role"
                                    placeholder="p. ej., médico de guardia"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label for="channel">Medio</Label>
                                <select
                                    id="channel"
                                    v-model="communicationForm.channel"
                                    class="h-9 rounded-md border bg-background px-2 text-sm"
                                >
                                    <option
                                        v-for="channel in props.channels"
                                        :key="channel.value"
                                        :value="channel.value"
                                    >
                                        {{ channel.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="communicationForm.successful"
                                    type="checkbox"
                                    class="size-4"
                                />
                                Se pudo comunicar
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="
                                        communicationForm.read_back_confirmed
                                    "
                                    type="checkbox"
                                    class="size-4"
                                />
                                El receptor repitió el resultado (relectura)
                            </label>
                        </div>
                        <div class="grid gap-1">
                            <Label for="communication_notes"
                                >Observaciones</Label
                            >
                            <Input
                                id="communication_notes"
                                v-model="communicationForm.notes"
                                placeholder="Opcional"
                            />
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :variant="
                                    communicationForm.successful
                                        ? 'default'
                                        : 'outline'
                                "
                                :disabled="communicationForm.processing"
                                data-test="save-communication"
                            >
                                <Phone class="size-4" />
                                {{
                                    communicationForm.successful
                                        ? 'Registrar comunicación'
                                        : 'Registrar intento fallido'
                                }}
                            </Button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <!-- Hemocultivos de la orden -->
                <section
                    class="grid gap-3 rounded-lg border p-4 text-sm"
                    data-test="order-summary"
                >
                    <h2 class="font-medium">Hemocultivos de la orden</h2>
                    <div
                        v-for="group in props.orderSummary"
                        :key="group.study"
                        class="grid gap-1"
                    >
                        <div class="font-semibold">{{ group.text }}</div>
                        <div
                            v-if="group.incubating || group.pending"
                            class="text-xs text-muted-foreground"
                        >
                            <template v-if="group.incubating"
                                >{{ group.incubating }} en incubación</template
                            >
                            <template v-if="group.incubating && group.pending">
                                ·
                            </template>
                            <template v-if="group.pending"
                                >{{ group.pending }} sin cargar</template
                            >
                        </div>
                        <Link
                            v-for="b in group.bottles"
                            :key="b.sample_study_id"
                            :href="showBottle(b.sample_study_id)"
                            class="flex items-center justify-between gap-2 rounded-md border px-2 py-1 hover:border-primary"
                            :class="{
                                'border-primary bg-muted/40':
                                    b.sample_study_id ===
                                    props.sample.sample_study_id,
                            }"
                        >
                            <span class="font-mono">{{ b.barcode }}</span>
                            <span class="text-right">
                                <Badge :class="statusClass[b.status]">{{
                                    b.status_label
                                }}</Badge>
                                <span
                                    v-if="b.minutes_to_positivity !== null"
                                    class="ml-1 text-xs text-muted-foreground"
                                    >{{
                                        formatMinutes(b.minutes_to_positivity)
                                    }}</span
                                >
                                <div
                                    v-if="b.gram"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ b.gram }}
                                </div>
                            </span>
                        </Link>
                    </div>
                </section>

                <!-- Informes -->
                <section class="grid gap-2 rounded-lg border p-4 text-sm">
                    <h2 class="flex items-center gap-2 font-medium">
                        <FileText class="size-4" /> Informes
                    </h2>
                    <p
                        v-if="props.reports.length === 0"
                        class="text-muted-foreground"
                    >
                        Ninguno todavía.
                    </p>
                    <Link
                        v-for="report in props.reports"
                        :key="report.ulid"
                        :href="report.url"
                        class="rounded-md border px-2 py-1 hover:border-primary"
                    >
                        {{ report.type_label }} v{{ report.version }}
                        <span class="text-xs text-muted-foreground"
                            >· {{ report.status_label }} ·
                            {{ formatDateTime(report.validated_at) }}</span
                        >
                    </Link>
                    <Button
                        v-if="canIssuePreliminary"
                        variant="outline"
                        data-test="issue-preliminary"
                        @click="issuePreliminary"
                        >Emitir informe preliminar</Button
                    >
                    <Link
                        v-if="
                            props.can.preliminary &&
                            props.studyStatus === 'ready_for_validation'
                        "
                        :href="validationRoute(props.sample.sample_study_id)"
                        class="rounded-md bg-primary px-3 py-1.5 text-center text-primary-foreground"
                        >Ir a validación</Link
                    >
                </section>
            </aside>
        </div>
    </div>
</template>
