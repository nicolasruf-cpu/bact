<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { Layers, Play, TestTubes } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import ReferenceTemplateNotice from '@/components/domain/ReferenceTemplateNotice.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { elapsedSince } from '@/lib/elapsed';
import { store as storeBatch } from '@/routes/batches';
import { inoculation } from '@/routes/workbenches';

type Medium = { code: string; name: string; inoculum: string };
type Item = {
    ulid: string;
    status: string;
    priority: string;
    priority_label: string;
    available_at: string;
    barcode: string;
    patient: string;
    sample_type: string;
    study: string;
    protocol_id: number;
    protocol: string;
    protocol_version: number;
    is_reference_template: boolean;
    media: Medium[];
};

const props = defineProps<{
    items: Item[];
    counters: { ready: number; in_progress: number; urgent: number };
    errors?: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mesada de siembra', href: inoculation() }],
    },
});

// Contadores y lista se refrescan solos (ADR-0005: polling).
usePoll(30_000, { only: ['items', 'counters'] });

// Reloj para el "tiempo desde la recepción".
const now = ref(new Date());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => (clock = setInterval(() => (now.value = new Date()), 30_000)));
onUnmounted(() => clearInterval(clock));

// Selección: por defecto, todas las listas (WORKBENCHES.md). Las que están en otra tanda no se eligen.
const ready = computed(() => props.items.filter((i) => i.status === 'ready'));
const selected = ref<Set<string>>(new Set());
const touched = ref(false);
watch(
    ready,
    (items) => {
        if (!touched.value) {
            selected.value = new Set(items.map((i) => i.ulid));
        } else {
            const available = new Set(items.map((i) => i.ulid));
            selected.value = new Set(
                [...selected.value].filter((u) => available.has(u)),
            );
        }
    },
    { immediate: true },
);

function toggle(ulid: string) {
    touched.value = true;
    const next = new Set(selected.value);
    if (next.has(ulid)) {
        next.delete(ulid);
    } else {
        next.add(ulid);
    }
    selected.value = next;
}

function toggleGroup(items: Item[], checked: boolean) {
    touched.value = true;
    const next = new Set(selected.value);
    for (const item of items.filter((i) => i.status === 'ready')) {
        if (checked) {
            next.add(item.ulid);
        } else {
            next.delete(item.ulid);
        }
    }
    selected.value = next;
}

const starting = ref(false);
function startBatch() {
    // Se respeta el orden de la mesada (prioridad y antigüedad).
    const activities = props.items
        .filter((i) => selected.value.has(i.ulid))
        .map((i) => i.ulid);
    starting.value = true;
    router.post(
        storeBatch().url,
        { type: 'inoculation', activities },
        { onFinish: () => (starting.value = false) },
    );
}

const groups = computed(() => {
    const byProtocol = new Map<
        number,
        { protocol: string; version: number; items: Item[] }
    >();
    for (const item of props.items) {
        const group = byProtocol.get(item.protocol_id) ?? {
            protocol: item.protocol,
            version: item.protocol_version,
            items: [],
        };
        group.items.push(item);
        byProtocol.set(item.protocol_id, group);
    }

    return [...byProtocol.values()];
});

const usesReferenceTemplate = computed(() =>
    props.items.some((i) => i.is_reference_template),
);
</script>

<template>
    <Head title="Mesada de siembra" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
                >
                    <TestTubes class="size-6" /> Mesada de siembra
                </h1>
                <div
                    class="mt-2 flex flex-wrap gap-2 text-sm"
                    data-test="counters"
                >
                    <Badge variant="secondary"
                        >{{ props.counters.ready }} listas</Badge
                    >
                    <Badge variant="outline"
                        >{{ props.counters.in_progress }} en tanda</Badge
                    >
                    <PriorityBadge
                        v-if="props.counters.urgent > 0"
                        priority="urgent"
                        :label="`${props.counters.urgent} urgentes`"
                    />
                </div>
            </div>

            <div class="grid justify-items-end gap-1">
                <Button
                    size="lg"
                    :disabled="selected.size === 0 || starting"
                    data-test="start-batch"
                    @click="startBatch"
                >
                    <Spinner v-if="starting" />
                    <Play v-else /> Iniciar tanda con {{ selected.size }}
                </Button>
                <InputError :message="props.errors?.activities" />
            </div>
        </div>

        <ReferenceTemplateNotice v-if="usesReferenceTemplate" />

        <div
            v-if="props.items.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            No hay muestras pendientes de siembra en esta sede.
        </div>

        <section
            v-for="group in groups"
            :key="group.protocol"
            class="overflow-hidden rounded-lg border"
        >
            <header
                class="flex items-center justify-between gap-2 bg-muted/50 px-3 py-2"
            >
                <label class="flex items-center gap-2 font-medium">
                    <input
                        type="checkbox"
                        :checked="
                            group.items
                                .filter((i) => i.status === 'ready')
                                .every((i) => selected.has(i.ulid))
                        "
                        :aria-label="`Seleccionar todas de ${group.protocol}`"
                        @change="
                            toggleGroup(
                                group.items,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ group.protocol }}
                    <span class="text-xs text-muted-foreground"
                        >v{{ group.version }}</span
                    >
                </label>
                <span class="text-sm text-muted-foreground">{{
                    group.items.length
                }}</span>
            </header>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="w-8 px-3 py-2">
                            <span class="sr-only">Elegir</span>
                        </th>
                        <th class="px-3 py-2 font-medium">Prioridad</th>
                        <th class="px-3 py-2 font-medium">Muestra</th>
                        <th class="px-3 py-2 font-medium">Paciente</th>
                        <th class="px-3 py-2 font-medium">Medios a sembrar</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Desde la recepción
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in group.items"
                        :key="item.ulid"
                        class="border-t"
                        :class="{ 'opacity-60': item.status !== 'ready' }"
                    >
                        <td class="px-3 py-2">
                            <input
                                v-if="item.status === 'ready'"
                                type="checkbox"
                                :checked="selected.has(item.ulid)"
                                :aria-label="`Elegir ${item.barcode}`"
                                @change="toggle(item.ulid)"
                            />
                            <Layers
                                v-else
                                class="size-4 text-muted-foreground"
                                aria-label="En una tanda"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <PriorityBadge
                                :priority="item.priority"
                                :label="item.priority_label"
                            />
                        </td>
                        <td class="px-3 py-2 font-mono font-medium">
                            {{ item.barcode }}
                            <Badge
                                v-if="item.status !== 'ready'"
                                variant="outline"
                                class="ml-1 font-sans"
                                >En tanda</Badge
                            >
                        </td>
                        <td class="px-3 py-2">{{ item.patient }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="medium in item.media"
                                    :key="medium.code"
                                    variant="outline"
                                    :title="`${medium.name} · ${medium.inoculum}`"
                                    >{{ medium.code }}</Badge
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    item.media[0]?.inoculum
                                }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ elapsedSince(item.available_at, now) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
