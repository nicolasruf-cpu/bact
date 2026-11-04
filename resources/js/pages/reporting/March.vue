<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Route } from '@lucide/vue';
import InterpretationBadge from '@/components/domain/InterpretationBadge.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import { formatDateTime } from '@/lib/datetime';
import type { SampleHeader } from '@/types/clinical';

type Step = {
    at: string | null;
    title: string;
    detail: string;
    by: string | null;
};

const props = defineProps<{
    sample: SampleHeader;
    study: { id: number; name: string; protocol: string; status: string };
    timeline: Step[];
    isolates: {
        number: number;
        morphotype: string;
        plate: string | null;
        count: string | null;
        significance: string;
        significance_reason: string | null;
        status: string;
        organism: string | null;
        no_ast_reason: string | null;
        steps: Step[];
        susceptibility: {
            panel: string;
            is_vitek: boolean;
            status: string;
            breakpoints: string | null;
            results: {
                antibiotic: string;
                method: string;
                is_additional: boolean;
                value: string | null;
                automatic: 'S' | 'I' | 'R' | null;
                final: 'S' | 'I' | 'R' | null;
                override_reason: string | null;
                rule_notes: string | null;
                is_reported: boolean;
                read_at: string | null;
            }[];
        }[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Marcha del estudio', href: '#' }],
    },
});
</script>

<template>
    <Head :title="`Marcha ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div>
            <h1 class="flex items-center gap-2 text-xl font-semibold">
                <Route class="size-5" /> Marcha · {{ props.study.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ props.study.protocol }} · {{ props.study.status }}. Cada paso
                sale de lo registrado, con su hora y quién lo hizo.
            </p>
        </div>

        <section class="grid gap-2 rounded-lg border p-4">
            <h2 class="font-medium">Muestra y cultivo</h2>
            <ol class="grid gap-2 border-l pl-4" data-test="march-timeline">
                <li
                    v-for="(step, i) in props.timeline"
                    :key="i"
                    class="text-sm"
                >
                    <div class="font-medium">
                        {{ step.title }}
                        <span class="text-xs font-normal text-muted-foreground">
                            · {{ formatDateTime(step.at) }}
                            <template v-if="step.by"> · {{ step.by }}</template>
                        </span>
                    </div>
                    <div v-if="step.detail" class="text-muted-foreground">
                        {{ step.detail }}
                    </div>
                </li>
            </ol>
        </section>

        <section
            v-for="isolate in props.isolates"
            :key="isolate.number"
            class="grid gap-3 rounded-lg border p-4"
            data-test="march-isolate"
        >
            <div>
                <h2 class="font-medium">
                    Aislamiento {{ isolate.number }}:
                    <em>{{ isolate.organism ?? 'sin identificar' }}</em>
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ isolate.morphotype }}
                    <template v-if="isolate.plate">
                        · {{ isolate.plate }}</template
                    >
                    <template v-if="isolate.count">
                        · {{ isolate.count }}</template
                    >
                    · {{ isolate.significance }}
                    <template v-if="isolate.significance_reason">
                        ({{ isolate.significance_reason }})</template
                    >
                    · {{ isolate.status }}
                </p>
                <p
                    v-if="isolate.no_ast_reason"
                    class="text-sm text-muted-foreground"
                >
                    Sin antibiograma: {{ isolate.no_ast_reason }}
                </p>
            </div>

            <ol v-if="isolate.steps.length" class="grid gap-2 border-l pl-4">
                <li v-for="(step, i) in isolate.steps" :key="i" class="text-sm">
                    <div class="font-medium">
                        {{ step.title }}
                        <span class="text-xs font-normal text-muted-foreground">
                            · {{ formatDateTime(step.at) }}
                            <template v-if="step.by"> · {{ step.by }}</template>
                        </span>
                    </div>
                    <div v-if="step.detail" class="text-muted-foreground">
                        {{ step.detail }}
                    </div>
                </li>
            </ol>

            <div
                v-for="(ast, k) in isolate.susceptibility"
                :key="k"
                class="grid gap-2"
            >
                <h3 class="text-sm font-medium">
                    Antibiograma · {{ ast.panel
                    }}{{ ast.is_vitek ? ' (VITEK)' : '' }} · {{ ast.status }}
                    <span
                        v-if="ast.breakpoints"
                        class="font-normal text-muted-foreground"
                    >
                        · {{ ast.breakpoints }}</span
                    >
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Antibiótico</th>
                                <th class="py-1 font-medium">Método</th>
                                <th class="py-1 text-right font-medium">
                                    Valor
                                </th>
                                <th class="py-1 text-center font-medium">
                                    Automática
                                </th>
                                <th class="py-1 text-center font-medium">
                                    Informada
                                </th>
                                <th class="py-1 font-medium">Cómo se llegó</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(r, j) in ast.results"
                                :key="j"
                                class="border-t align-top"
                            >
                                <td class="py-1">
                                    {{ r.antibiotic }}
                                    <span
                                        v-if="r.is_additional"
                                        class="block text-xs text-muted-foreground"
                                        >fuera del panel</span
                                    >
                                </td>
                                <td class="py-1">{{ r.method }}</td>
                                <td class="py-1 text-right tabular-nums">
                                    {{ r.value ?? '—' }}
                                </td>
                                <td class="py-1 text-center">
                                    <InterpretationBadge :value="r.automatic" />
                                </td>
                                <td class="py-1 text-center">
                                    <InterpretationBadge :value="r.final" />
                                    <span
                                        v-if="!r.is_reported"
                                        class="block text-xs text-muted-foreground"
                                        >no se informa</span
                                    >
                                </td>
                                <td class="py-1 text-xs text-muted-foreground">
                                    <template v-if="!r.read_at"
                                        >Sin leer</template
                                    >
                                    <template v-else-if="r.override_reason"
                                        >Cambio:
                                        {{ r.override_reason }}</template
                                    >
                                    <template v-else-if="r.automatic"
                                        >Punto de corte</template
                                    >
                                    <template v-else
                                        >Ingresada por el bacteriólogo</template
                                    >
                                    <span v-if="r.rule_notes" class="block">{{
                                        r.rule_notes
                                    }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
