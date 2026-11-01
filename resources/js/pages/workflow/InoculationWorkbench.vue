<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { Play, TestTubes } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import ReferenceTemplateNotice from '@/components/domain/ReferenceTemplateNotice.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { elapsedSince } from '@/lib/elapsed';
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
                        >{{ props.counters.in_progress }} en proceso</Badge
                    >
                    <PriorityBadge
                        v-if="props.counters.urgent > 0"
                        priority="urgent"
                        :label="`${props.counters.urgent} urgentes`"
                    />
                </div>
            </div>

            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span tabindex="0">
                            <Button size="lg" disabled>
                                <Play /> Iniciar tanda de siembra
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        >Disponible en la próxima entrega</TooltipContent
                    >
                </Tooltip>
            </TooltipProvider>
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
                <h2 class="font-medium">
                    {{ group.protocol }}
                    <span class="text-xs text-muted-foreground"
                        >v{{ group.version }}</span
                    >
                </h2>
                <span class="text-sm text-muted-foreground">{{
                    group.items.length
                }}</span>
            </header>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
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
                    >
                        <td class="px-3 py-2">
                            <PriorityBadge
                                :priority="item.priority"
                                :label="item.priority_label"
                            />
                        </td>
                        <td class="px-3 py-2 font-mono font-medium">
                            {{ item.barcode }}
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
