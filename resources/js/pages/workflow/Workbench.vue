<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, Clock } from '@lucide/vue';
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/datetime';
import { elapsedSince } from '@/lib/elapsed';

type Item = {
    ulid: string;
    type: string;
    type_label: string;
    status: string;
    priority: string;
    priority_label: string;
    available_at: string;
    due_at: string | null;
    overdue: boolean;
    barcode: string;
    patient: string;
    sample_type: string;
    detail: string;
    url: string | null;
};

const props = defineProps<{
    workbench: { key: string; title: string };
    items: Item[];
    counters: {
        ready: number;
        urgent: number;
        overdue: number;
        scheduled: number;
        next_at: string | null;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Mesadas', href: '/dashboard' }] },
});

usePoll(30_000, { only: ['items', 'counters'] });
</script>

<template>
    <Head :title="props.workbench.title" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ props.workbench.title }}
            </h1>
            <div class="mt-2 flex flex-wrap gap-2 text-sm" data-test="counters">
                <Badge variant="secondary"
                    >{{ props.counters.ready }} para hacer</Badge
                >
                <PriorityBadge
                    v-if="props.counters.urgent"
                    priority="urgent"
                    :label="`${props.counters.urgent} urgentes`"
                />
                <Badge
                    v-if="props.counters.overdue"
                    class="gap-1 bg-orange-500 text-black hover:bg-orange-500"
                    ><Clock class="size-3" />
                    {{ props.counters.overdue }} demoradas</Badge
                >
                <Badge
                    v-if="props.counters.scheduled"
                    variant="outline"
                    class="gap-1"
                >
                    <CalendarClock class="size-3" />
                    {{ props.counters.scheduled }} programadas
                    <template v-if="props.counters.next_at"
                        >· próxima
                        {{ formatDateTime(props.counters.next_at) }}</template
                    >
                </Badge>
            </div>
        </div>

        <div
            v-if="props.items.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            No hay trabajo disponible en esta mesada.
        </div>

        <div v-else class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">Prioridad</th>
                        <th class="px-3 py-2 font-medium">Muestra</th>
                        <th class="px-3 py-2 font-medium">Paciente</th>
                        <th class="px-3 py-2 font-medium">Actividad</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Disponible desde
                        </th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in props.items"
                        :key="item.ulid"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <PriorityBadge
                                :priority="item.priority"
                                :label="item.priority_label"
                            />
                            <Badge
                                v-if="item.overdue"
                                class="ml-1 gap-1 bg-orange-500 text-black hover:bg-orange-500"
                                ><Clock class="size-3" /> Demorada</Badge
                            >
                        </td>
                        <td class="px-3 py-2 font-mono font-medium">
                            {{ item.barcode }}
                        </td>
                        <td class="px-3 py-2">
                            {{ item.patient }}
                            <div class="text-xs text-muted-foreground">
                                {{ item.sample_type }}
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ item.type_label }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ item.detail }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            hace {{ elapsedSince(item.available_at) }}
                        </td>
                        <td class="px-2">
                            <Link
                                v-if="item.url"
                                :href="item.url"
                                class="inline-flex items-center gap-1 rounded-md bg-primary px-3 py-1.5 text-primary-foreground"
                                :aria-label="`Abrir ${item.barcode}`"
                                data-test="open-item"
                                >Abrir <ChevronRight class="size-4"
                            /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
