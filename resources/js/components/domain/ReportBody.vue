<script setup lang="ts">
import InterpretationBadge from '@/components/domain/InterpretationBadge.vue';
import { formatDateTime } from '@/lib/datetime';
import { formatMinutes } from '@/lib/elapsed';
import type { ReportContent } from '@/types/clinical';

defineProps<{ content: ReportContent }>();
</script>

<template>
    <article class="grid gap-5 text-sm print:text-xs">
        <header class="grid gap-1 border-b pb-3">
            <p class="text-xs tracking-wide text-muted-foreground uppercase">
                Laboratorio RUF · {{ content.site }}
            </p>
            <h2 class="text-xl font-semibold">{{ content.study }}</h2>
            <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <div>
                    <span class="text-muted-foreground">Paciente:</span>
                    {{ content.patient.name }}
                </div>
                <div>
                    <span class="text-muted-foreground">Documento:</span>
                    {{ content.patient.document_number }}
                </div>
                <div>
                    <span class="text-muted-foreground">Sexo:</span>
                    {{ content.patient.sex }}
                </div>
                <div v-if="content.patient.birth_date">
                    <span class="text-muted-foreground">Nacimiento:</span>
                    {{ content.patient.birth_date }}
                </div>
                <div>
                    <span class="text-muted-foreground">Orden:</span>
                    {{ content.order.number ?? 's/n' }}
                </div>
                <div v-if="content.order.physician">
                    <span class="text-muted-foreground">Médico:</span>
                    {{ content.order.physician }}
                </div>
                <div>
                    <span class="text-muted-foreground">Muestra:</span>
                    {{ content.sample.type }} ·
                    <span class="font-mono">{{ content.sample.barcode }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Recibida:</span>
                    {{ formatDateTime(content.sample.received_at) }}
                </div>
            </div>
        </header>

        <section class="grid gap-1">
            <h3 class="font-semibold">Resultado</h3>
            <p class="text-base" data-test="report-result">
                {{ content.result ?? '—' }}
            </p>
            <p v-if="content.reading_notes" class="text-muted-foreground">
                {{ content.reading_notes }}
            </p>
        </section>

        <section
            v-if="content.blood_culture"
            class="grid gap-1"
            data-test="report-blood-culture"
        >
            <h3 class="font-semibold">Frasco</h3>
            <p>
                {{ content.blood_culture.bottle_type }} ·
                {{ content.blood_culture.method }}
                <template v-if="content.blood_culture.instrument">
                    ({{ content.blood_culture.instrument }})</template
                >
                · cargado {{ formatDateTime(content.blood_culture.loaded_at) }}
            </p>
            <p v-if="content.blood_culture.positive_at">
                Positivo {{ formatDateTime(content.blood_culture.positive_at) }}
                <template
                    v-if="content.blood_culture.minutes_to_positivity !== null"
                >
                    · tiempo hasta la positividad:
                    {{
                        formatMinutes(
                            content.blood_culture.minutes_to_positivity,
                        )
                    }}</template
                >
                <template v-else-if="content.blood_culture.positivity_source">
                    · {{ content.blood_culture.positivity_source }}</template
                >
            </p>
            <p v-if="content.blood_culture.gram" class="text-base">
                <span class="text-muted-foreground">Gram:</span>
                {{ content.blood_culture.gram.result }}
                <template v-if="content.blood_culture.gram.description">
                    — {{ content.blood_culture.gram.description }}</template
                >
            </p>
            <div
                v-if="content.blood_culture.order_summary.length"
                class="mt-1 rounded-md border p-2"
            >
                <p class="text-xs text-muted-foreground">
                    Hemocultivos de la orden al emitir este informe:
                </p>
                <p
                    v-for="group in content.blood_culture.order_summary"
                    :key="group.study"
                    class="font-medium"
                >
                    {{ group.text }}
                    <span
                        v-if="group.incubating || group.pending"
                        class="font-normal text-muted-foreground"
                    >
                        ({{ group.incubating + group.pending }} sin resultado
                        todavía)</span
                    >
                </p>
            </div>
        </section>

        <section
            v-for="isolate in content.isolates"
            :key="isolate.number"
            class="grid gap-2"
        >
            <h3 class="font-semibold">
                Aislamiento {{ isolate.number }}:
                <em>{{ isolate.organism ?? 'sin identificar' }}</em>
                <span
                    v-if="isolate.count"
                    class="font-normal text-muted-foreground"
                >
                    · {{ isolate.count }}</span
                >
            </h3>
            <p v-if="isolate.no_ast_reason" class="text-muted-foreground">
                Sin antibiograma: {{ isolate.no_ast_reason }}
            </p>

            <template v-if="isolate.susceptibility">
                <p class="text-xs text-muted-foreground">
                    Antibiograma · {{ isolate.susceptibility.method }}
                    <template v-if="isolate.susceptibility.breakpoints">
                        · Puntos de corte:
                        {{ isolate.susceptibility.breakpoints.name }} ({{
                            isolate.susceptibility.breakpoints.edition
                        }})
                    </template>
                </p>
                <p
                    v-if="isolate.susceptibility.breakpoints?.is_synthetic"
                    class="rounded-md border border-red-500/50 bg-red-50 p-2 text-xs font-medium text-red-800 dark:bg-red-950/40 dark:text-red-200"
                >
                    Interpretación con puntos de corte FICTICIOS de
                    demostración: NO CLÍNICA.
                </p>
                <table class="w-full max-w-lg text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="py-1 font-medium">Antibiótico</th>
                            <th class="py-1 text-right font-medium">
                                {{ isolate.susceptibility.unit }}
                            </th>
                            <th class="py-1 text-center font-medium">
                                Interpretación
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="result in isolate.susceptibility.results"
                            :key="result.antibiotic"
                            class="border-t"
                        >
                            <td class="py-1">{{ result.antibiotic }}</td>
                            <td class="py-1 text-right tabular-nums">
                                {{ result.value ?? '—' }}
                            </td>
                            <td class="py-1 text-center">
                                <InterpretationBadge
                                    :value="result.interpretation"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </section>

        <footer class="border-t pt-2 text-xs text-muted-foreground">
            {{ content.protocol.name }} v{{ content.protocol.version }}
            <strong
                v-if="content.protocol.is_reference_template"
                class="text-amber-700 dark:text-amber-300"
            >
                · Plantilla de referencia no validada: no usar con pacientes
                reales.</strong
            >
        </footer>
    </article>
</template>
