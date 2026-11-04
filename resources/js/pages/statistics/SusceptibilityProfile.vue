<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, TriangleAlert } from '@lucide/vue';
import { reactive } from 'vue';
import DateInput from '@/components/DateInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { susceptibility } from '@/routes/statistics';

type Antibiotic = {
    code: string;
    name: string;
    tested: number;
    susceptible: number;
    intermediate: number;
    resistant: number;
    percent_susceptible: number | null;
};

const props = defineProps<{
    filters: { from: string; to: string };
    site: string;
    minIsolates: number;
    organisms: {
        organism: string;
        isolates: number;
        insufficient: boolean;
        antibiotics: Antibiotic[];
    }[];
    total_isolates: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Perfil de sensibilidad', href: susceptibility() },
        ],
    },
});

const filters = reactive({ ...props.filters });

function apply() {
    router.get(susceptibility().url, filters, { preserveState: true });
}

// Celda: el porcentaje se lee como texto; el color es solo apoyo (UX_UI.md).
function tone(percent: number | null): string {
    if (percent === null) {
        return '';
    }
    if (percent >= 90) {
        return 'bg-green-100 dark:bg-green-950';
    }

    return percent >= 60
        ? 'bg-amber-100 dark:bg-amber-950'
        : 'bg-red-100 dark:bg-red-950';
}
</script>

<template>
    <Head title="Perfil de sensibilidad" />

    <div class="grid gap-4 p-4">
        <div>
            <h1
                class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
            >
                <BarChart3 class="size-6" /> Perfil de sensibilidad
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ props.site }} · {{ props.total_isolates }} aislamientos ·
                primer aislamiento por paciente y especie, solo informes
                validados (metodología de referencia CLSI M39, a validar por el
                laboratorio).
            </p>
        </div>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <div class="grid gap-1">
                <Label for="from">Desde</Label>
                <DateInput id="from" v-model="filters.from" />
            </div>
            <div class="grid gap-1">
                <Label for="to">Hasta</Label>
                <DateInput id="to" v-model="filters.to" />
            </div>
            <Button type="submit" variant="outline">Aplicar</Button>
        </form>

        <div
            v-if="props.organisms.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            No hay antibiogramas validados en el período.
        </div>

        <section
            v-for="organism in props.organisms"
            :key="organism.organism"
            class="overflow-x-auto rounded-lg border"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-2 bg-muted/50 px-3 py-2"
            >
                <h2 class="font-medium">
                    <em>{{ organism.organism }}</em> ·
                    {{ organism.isolates }} aislamientos
                </h2>
                <span
                    v-if="organism.insufficient"
                    class="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300"
                >
                    <TriangleAlert class="size-4" /> Menos de
                    {{ props.minIsolates }} aislamientos: interpretar con
                    cautela
                </span>
            </header>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">Antibiótico</th>
                        <th class="px-3 py-2 text-right font-medium">
                            % Sensible
                        </th>
                        <th class="px-3 py-2 text-right font-medium">S</th>
                        <th class="px-3 py-2 text-right font-medium">I</th>
                        <th class="px-3 py-2 text-right font-medium">R</th>
                        <th class="px-3 py-2 text-right font-medium">
                            Probados
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="antibiotic in organism.antibiotics"
                        :key="antibiotic.code"
                        class="border-t"
                    >
                        <td class="px-3 py-1.5">{{ antibiotic.name }}</td>
                        <td
                            class="px-3 py-1.5 text-right font-semibold tabular-nums"
                            :class="tone(antibiotic.percent_susceptible)"
                        >
                            {{ antibiotic.percent_susceptible ?? '—'
                            }}<template
                                v-if="antibiotic.percent_susceptible !== null"
                            >
                                %</template
                            >
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">
                            {{ antibiotic.susceptible }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">
                            {{ antibiotic.intermediate }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">
                            {{ antibiotic.resistant }}
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">
                            {{ antibiotic.tested }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
