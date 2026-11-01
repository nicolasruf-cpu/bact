<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, Clock, Droplets } from '@lucide/vue';
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

type Bottle = {
    ulid: string;
    barcode: string;
    patient: string;
    study: string;
    method: string;
    method_label: string;
    instrument_position: string | null;
    loaded_at: string;
    day: number;
    incubation_ends_at: string;
    url: string;
};

const props = defineProps<{
    items: Item[];
    incubating: Bottle[];
    counters: {
        ready: number;
        urgent: number;
        overdue: number;
        scheduled: number;
        next_at: string | null;
        incubating: number;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Mesadas', href: '/dashboard' }] },
});

usePoll(30_000, { only: ['items', 'incubating', 'counters'] });
</script>

<template>
    <Head title="Mesada de hemocultivos" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1
                class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
            >
                <Droplets class="size-6" /> Mesada de hemocultivos
            </h1>
            <div class="mt-2 flex flex-wrap gap-2 text-sm" data-test="counters">
                <Badge variant="secondary"
                    >{{ props.counters.ready }} para hacer</Badge
                >
                <PriorityBadge
                    v-if="props.counters.urgent"
                    priority="stat"
                    :label="`${props.counters.urgent} urgentes`"
                />
                <Badge
                    v-if="props.counters.overdue"
                    class="gap-1 bg-orange-500 text-black hover:bg-orange-500"
                    ><Clock class="size-3" />
                    {{ props.counters.overdue }} demoradas</Badge
                >
                <Badge variant="outline"
                    >{{ props.counters.incubating }} frascos en
                    incubación</Badge
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

        <section class="grid gap-2">
            <h2 class="font-medium">Para hacer</h2>
            <div
                v-if="props.items.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
            >
                No hay trabajo disponible.
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
                            :class="{
                                'bg-red-50 dark:bg-red-950/30':
                                    item.priority === 'stat',
                            }"
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
                                <div class="font-medium">
                                    {{ item.type_label }}
                                </div>
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
        </section>

        <section class="grid gap-2">
            <h2 class="font-medium">Frascos en incubación</h2>
            <p class="text-sm text-muted-foreground">
                Cuando el equipo da la alarma de positivo, abrí el frasco y
                registralo con la hora que informa el equipo.
            </p>
            <div
                v-if="props.incubating.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
            >
                No hay frascos en incubación.
            </div>
            <div v-else class="overflow-hidden rounded-lg border">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Frasco</th>
                            <th class="px-3 py-2 font-medium">Paciente</th>
                            <th class="px-3 py-2 font-medium">Procesamiento</th>
                            <th class="px-3 py-2 font-medium">Cargado</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Día
                            </th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="bottle in props.incubating"
                            :key="bottle.ulid"
                            class="border-t hover:bg-muted/30"
                        >
                            <td class="px-3 py-2">
                                <span class="font-mono font-medium">{{
                                    bottle.barcode
                                }}</span>
                                <div class="text-xs text-muted-foreground">
                                    {{ bottle.study }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ bottle.patient }}</td>
                            <td class="px-3 py-2">
                                {{ bottle.method_label }}
                                <span
                                    v-if="bottle.instrument_position"
                                    class="text-xs text-muted-foreground"
                                >
                                    · posición
                                    {{ bottle.instrument_position }}</span
                                >
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ formatDateTime(bottle.loaded_at) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ bottle.day }}
                            </td>
                            <td class="px-2">
                                <Link
                                    :href="bottle.url"
                                    class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 hover:border-primary"
                                    :aria-label="`Abrir ${bottle.barcode}`"
                                    >Abrir <ChevronRight class="size-4"
                                /></Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
