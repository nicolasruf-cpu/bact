<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleDashed,
    CornerUpLeft,
    Pause,
    Play,
    Square,
    TestTubes,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import ReferenceTemplateNotice from '@/components/domain/ReferenceTemplateNotice.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    finish as finishBatch,
    pause as pauseBatch,
    resume as resumeBatch,
    show as showBatch,
} from '@/routes/batches';
import { remove as removeItemRoute } from '@/routes/batches/items';
import {
    bulk as bulkInoculation,
    store as storeInoculation,
} from '@/routes/inoculations';

type Medium = {
    id: number;
    code: string;
    name: string;
    inoculum: string;
    incubation: string;
};
type Activity = {
    ulid: string;
    priority: string;
    priority_label: string;
    sample_study_id: number;
    barcode: string;
    patient: string;
    order_number: string | null;
    sample_type: string;
    study: string;
    protocol: string;
    protocol_version: number;
    is_reference_template: boolean;
    media: Medium[];
};
type Item = {
    id: number;
    position: number;
    status: 'pending' | 'done' | 'removed' | 'released';
    status_label: string;
    removed_reason: string | null;
    activity: Activity | null;
};
type Batch = {
    ulid: string;
    code: string;
    type: string;
    type_label: string;
    status: 'in_progress' | 'paused' | 'completed' | 'cancelled';
    status_label: string;
    owner: string;
    items: Item[];
};

const props = defineProps<{ batch: Batch }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tanda', href: '#' }],
    },
});

const pending = computed(() =>
    props.batch.items.filter((i) => i.status === 'pending'),
);
const done = computed(
    () => props.batch.items.filter((i) => i.status === 'done').length,
);
const isOpen = computed(() => props.batch.status === 'in_progress');

// Ítem actual: el elegido, o el primer pendiente (al reanudar se abre ahí).
const currentId = ref<number | null>(null);
const current = computed<Item | undefined>(
    () =>
        props.batch.items.find(
            (i) => i.id === currentId.value && i.status === 'pending',
        ) ?? pending.value[0],
);

// --- Registro de la siembra del ítem actual ---------------------------------

const form = useForm({
    medium_ids: [] as number[],
    reason: '',
    batch: props.batch.ulid,
});

watch(
    () => current.value?.id,
    () => {
        form.medium_ids = current.value?.activity?.media.map((m) => m.id) ?? [];
        form.reason = '';
        form.clearErrors();
    },
    { immediate: true },
);

const modified = computed(
    () =>
        (current.value?.activity?.media.length ?? 0) !== form.medium_ids.length,
);

function saveAndNext() {
    const activity = current.value?.activity;
    if (!activity || !isOpen.value || form.processing) {
        return;
    }
    form.transform((data) => ({
        ...data,
        medium_ids: modified.value ? data.medium_ids : null,
        reason: modified.value ? data.reason : null,
    })).post(storeInoculation(activity.sample_study_id).url, {
        preserveScroll: true,
        onSuccess: () => (currentId.value = null),
    });
}

// --- Confirmación múltiple (solo protocolo estándar) ------------------------

const bulkOpen = ref(false);
const bulkProcessing = ref(false);
function confirmBulk() {
    bulkProcessing.value = true;
    router.post(
        bulkInoculation().url,
        {
            sample_study_ids: pending.value
                .map((i) => i.activity?.sample_study_id)
                .filter(Boolean),
            batch: props.batch.ulid,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                bulkProcessing.value = false;
                bulkOpen.value = false;
            },
        },
    );
}

// --- Retirar ítem ------------------------------------------------------------

const removing = ref<Item | null>(null);
const removeForm = useForm({ reason: '' });
function removeCurrent() {
    if (!removing.value) {
        return;
    }
    removeForm.post(
        removeItemRoute([props.batch.ulid, removing.value.id]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                removing.value = null;
                removeForm.reset();
            },
        },
    );
}

// --- Estado de la tanda -------------------------------------------------------

function pause() {
    router.post(pauseBatch(props.batch.ulid).url, {}, { preserveScroll: true });
}
function resume() {
    router.post(
        resumeBatch(props.batch.ulid).url,
        {},
        { preserveScroll: true },
    );
}
const finishOpen = ref(false);
function finish() {
    router.post(finishBatch(props.batch.ulid).url);
}

// --- Teclado: J/K navegar, Enter guardar y seguir, Esc pausar ----------------

function onKey(event: KeyboardEvent) {
    const target = event.target as HTMLElement;
    const typing =
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) &&
        (target as HTMLInputElement).type !== 'checkbox';
    if (bulkOpen.value || removing.value || finishOpen.value) {
        return;
    }
    if (event.key === 'Escape' && isOpen.value) {
        pause();
    } else if (!typing && (event.key === 'j' || event.key === 'k')) {
        const list = pending.value;
        const index = list.findIndex((i) => i.id === current.value?.id);
        const next =
            list[
                Math.min(
                    list.length - 1,
                    Math.max(0, index + (event.key === 'j' ? 1 : -1)),
                )
            ];
        currentId.value = next?.id ?? null;
    } else if (
        event.key === 'Enter' &&
        !typing &&
        target.tagName !== 'BUTTON'
    ) {
        event.preventDefault();
        saveAndNext();
    }
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="`Tanda ${props.batch.code}`" />

    <div class="flex h-full flex-col gap-4 p-4">
        <!-- Encabezado -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    <TestTubes class="size-6" /> Tanda de
                    {{ props.batch.type_label.toLowerCase() }}
                    <span class="font-mono text-lg text-muted-foreground">{{
                        props.batch.code
                    }}</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.batch.status_label }} · {{ props.batch.owner }} ·
                    <span class="tabular-nums" data-test="progress"
                        >{{ done }}/{{ props.batch.items.length }}</span
                    >
                    hechas
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="isOpen" variant="outline" @click="pause"
                    ><Pause /> Pausar (Esc)</Button
                >
                <Button
                    v-else-if="props.batch.status === 'paused'"
                    @click="resume"
                    ><Play /> Reanudar</Button
                >
                <Button
                    v-if="
                        props.batch.status === 'in_progress' ||
                        props.batch.status === 'paused'
                    "
                    variant="outline"
                    @click="finishOpen = true"
                    ><Square /> Finalizar tanda</Button
                >
            </div>
        </div>

        <div
            v-if="props.batch.status === 'paused'"
            class="rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Tanda en pausa. Al reanudar, seguís en el primer ítem pendiente.
        </div>

        <ReferenceTemplateNotice
            v-if="current?.activity?.is_reference_template"
        />

        <div
            class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]"
        >
            <!-- Lista de ítems -->
            <ol
                class="grid content-start gap-1 overflow-y-auto rounded-lg border p-2"
                aria-label="Ítems de la tanda"
            >
                <li v-for="item in props.batch.items" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm"
                        :class="[
                            item.id === current?.id
                                ? 'bg-primary/10 ring-1 ring-primary'
                                : 'hover:bg-muted',
                            item.status !== 'pending'
                                ? 'text-muted-foreground'
                                : '',
                        ]"
                        :disabled="item.status !== 'pending'"
                        @click="currentId = item.id"
                    >
                        <CheckCircle2
                            v-if="item.status === 'done'"
                            class="size-4 text-green-600"
                            aria-label="Hecho"
                        />
                        <XCircle
                            v-else-if="item.status === 'removed'"
                            class="size-4"
                            aria-label="Retirado"
                        />
                        <CornerUpLeft
                            v-else-if="item.status === 'released'"
                            class="size-4"
                            aria-label="Devuelto"
                        />
                        <CircleDashed
                            v-else
                            class="size-4"
                            aria-label="Pendiente"
                        />
                        <span class="w-6 text-xs tabular-nums">{{
                            item.position
                        }}</span>
                        <span class="font-mono font-medium">{{
                            item.activity?.barcode
                        }}</span>
                        <PriorityBadge
                            v-if="
                                item.activity &&
                                item.activity.priority !== 'routine'
                            "
                            :priority="item.activity.priority"
                        />
                        <span
                            v-if="item.status !== 'pending'"
                            class="ml-auto text-xs"
                            >{{ item.status_label }}</span
                        >
                    </button>
                </li>
            </ol>

            <!-- Ítem actual -->
            <section class="rounded-lg border p-4">
                <div
                    v-if="!current"
                    class="grid h-full place-items-center gap-3 p-8 text-center"
                >
                    <CheckCircle2 class="size-10 text-green-600" />
                    <p class="text-lg font-medium">
                        No quedan ítems pendientes.
                    </p>
                    <Button @click="finishOpen = true"
                        ><Square /> Finalizar tanda</Button
                    >
                </div>

                <form
                    v-else-if="current.activity"
                    class="grid gap-5"
                    @submit.prevent="saveAndNext"
                >
                    <!-- Identidad de la muestra, siempre visible (UX_UI.md) -->
                    <div class="grid gap-1 border-b pb-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="font-mono text-3xl font-semibold"
                                data-test="current-barcode"
                                >{{ current.activity.barcode }}</span
                            >
                            <PriorityBadge
                                :priority="current.activity.priority"
                                :label="current.activity.priority_label"
                            />
                        </div>
                        <div class="text-lg">
                            {{ current.activity.patient }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ current.activity.sample_type }} ·
                            {{ current.activity.study }} ·
                            {{ current.activity.protocol }} v{{
                                current.activity.protocol_version
                            }}
                            <template v-if="current.activity.order_number">
                                · Orden
                                <span class="font-mono">{{
                                    current.activity.order_number
                                }}</span>
                            </template>
                        </div>
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">
                            Medios sembrados
                        </legend>
                        <label
                            v-for="medium in current.activity.media"
                            :key="medium.id"
                            class="flex cursor-pointer items-center gap-3 rounded-md border p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                v-model="form.medium_ids"
                                type="checkbox"
                                :value="medium.id"
                                class="size-4"
                            />
                            <span class="font-medium">{{ medium.name }}</span>
                            <span class="text-sm text-muted-foreground"
                                >{{ medium.inoculum }} ·
                                {{ medium.incubation }}</span
                            >
                        </label>
                        <InputError :message="form.errors.medium_ids" />
                    </fieldset>

                    <div v-if="modified" class="grid gap-2">
                        <Label for="reason"
                            >Motivo de la modificación del protocolo</Label
                        >
                        <Input
                            id="reason"
                            v-model="form.reason"
                            placeholder="p. ej., sin medio en stock"
                        />
                        <InputError :message="form.errors.reason" />
                    </div>
                    <InputError
                        :message="(form.errors as Record<string, string>).study"
                    />

                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t pt-4"
                    >
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                :disabled="!isOpen"
                                @click="removing = current"
                            >
                                Retirar de la tanda
                            </Button>
                            <Button
                                v-if="pending.length > 1"
                                type="button"
                                variant="outline"
                                :disabled="!isOpen"
                                data-test="bulk-open"
                                @click="bulkOpen = true"
                            >
                                Confirmar {{ pending.length }} restantes con
                                protocolo estándar…
                            </Button>
                        </div>
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="!isOpen || form.processing"
                            data-test="save-next"
                        >
                            <Spinner v-if="form.processing" />
                            Sembrado · siguiente (Enter)
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <!-- Confirmación múltiple -->
    <Dialog :open="bulkOpen" @update:open="bulkOpen = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Confirmar {{ pending.length }} siembras con el protocolo
                    estándar</DialogTitle
                >
                <DialogDescription>
                    Se registran como sembradas con todos los medios del
                    protocolo, sin modificaciones. Revisá la lista antes de
                    confirmar.
                </DialogDescription>
            </DialogHeader>
            <ul class="max-h-64 overflow-y-auto rounded-md border p-2 text-sm">
                <li
                    v-for="item in pending"
                    :key="item.id"
                    class="flex gap-2 py-0.5"
                >
                    <span class="font-mono font-medium">{{
                        item.activity?.barcode
                    }}</span>
                    <span class="truncate text-muted-foreground">{{
                        item.activity?.patient
                    }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">{{
                        item.activity?.media.map((m) => m.code).join(' + ')
                    }}</span>
                </li>
            </ul>
            <DialogFooter>
                <Button variant="ghost" @click="bulkOpen = false"
                    >Cancelar</Button
                >
                <Button
                    :disabled="bulkProcessing"
                    data-test="bulk-confirm"
                    @click="confirmBulk"
                >
                    <Spinner v-if="bulkProcessing" /> Confirmar
                    {{ pending.length }} siembras
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Retirar ítem -->
    <Dialog
        :open="removing !== null"
        @update:open="(open) => !open && (removing = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Retirar {{ removing?.activity?.barcode }} de la
                    tanda</DialogTitle
                >
                <DialogDescription
                    >Vuelve a la mesada de siembra. El motivo queda
                    registrado.</DialogDescription
                >
            </DialogHeader>
            <form class="grid gap-2" @submit.prevent="removeCurrent">
                <Label for="remove-reason">Motivo</Label>
                <Input id="remove-reason" v-model="removeForm.reason" v-focus />
                <InputError :message="removeForm.errors.reason" />
                <DialogFooter class="mt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="removing = null"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="removeForm.processing"
                        >Retirar</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Finalizar -->
    <Dialog :open="finishOpen" @update:open="finishOpen = $event">
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Finalizar la tanda {{ props.batch.code }}</DialogTitle
                >
                <DialogDescription>
                    <template v-if="pending.length">
                        Quedan {{ pending.length }} ítems pendientes: vuelven a
                        la mesada para que los tome alguien más o una tanda
                        nueva.
                    </template>
                    <template v-else
                        >Todos los ítems están registrados.</template
                    >
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="ghost" @click="finishOpen = false"
                    >Cancelar</Button
                >
                <Button data-test="finish-confirm" @click="finish"
                    >Finalizar</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <a :href="showBatch(props.batch.ulid).url" class="sr-only"
        >Recargar tanda</a
    >
</template>
