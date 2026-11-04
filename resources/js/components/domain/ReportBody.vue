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

        <section
            v-if="content.direct_exam"
            class="grid gap-1"
            data-test="report-direct-exam"
        >
            <h3 class="font-semibold">{{ content.direct_exam.label }}</h3>
            <p v-for="line in content.direct_exam.lines" :key="line.field">
                <span class="text-muted-foreground">{{ line.field }}:</span>
                {{ line.values.join(', ') }}
            </p>
            <p v-if="content.direct_exam.description">
                {{ content.direct_exam.description }}
            </p>
        </section>

        <section class="grid gap-1">
            <h3 class="font-semibold">Resultado</h3>
            <p class="text-base" data-test="report-result">
                {{ content.result ?? '—' }}
                <template v-if="content.result_count">
                    · {{ content.result_count }}</template
                >
            </p>
            <p
                v-if="content.rejection?.new_sample_requested_to"
                data-test="report-new-sample"
            >
                Se solicitó una nueva muestra ({{
                    content.rejection.new_sample_requested_to
                }}).
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
            <p
                v-for="test in isolate.tests ?? []"
                :key="test.name"
                data-test="report-isolate-test"
            >
                <span class="text-muted-foreground">{{ test.name }}:</span>
                {{ test.result }}
            </p>
            <p
                v-for="(referral, i) in isolate.referrals ?? []"
                :key="i"
                data-test="report-referral"
            >
                <span class="text-muted-foreground"
                    >Derivado a {{ referral.destination }} ({{
                        referral.reason
                    }}):</span
                >
                {{ referral.result ?? 'resultado pendiente' }}
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
                            <th class="py-1 text-right font-medium">Valor</th>
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
                            <td class="py-1">
                                {{ result.antibiotic }}
                                <div
                                    v-if="result.comment"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ result.comment }}
                                </div>
                            </td>
                            <td class="py-1 text-right tabular-nums">
                                {{ result.value ?? '—' }}
                                <span
                                    v-if="result.value"
                                    class="text-xs text-muted-foreground"
                                    >{{
                                        result.unit ??
                                        isolate.susceptibility.unit
                                    }}</span
                                >
                            </td>
                            <td class="py-1 text-center">
                                <InterpretationBadge
                                    :value="result.interpretation"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="isolate.susceptibility.pending"
                    class="text-xs text-muted-foreground"
                >
                    En curso: {{ isolate.susceptibility.pending }}
                    {{
                        isolate.susceptibility.pending === 1
                            ? 'antibiótico'
                            : 'antibióticos'
                    }}
                    más, que se informan cuando se lean.
                </p>
            </template>
        </section>

        <section
            v-if="content.other_isolates?.length"
            class="grid gap-1"
            data-test="report-other-isolates"
        >
            <h3 class="font-semibold">Otros desarrollos</h3>
            <p v-for="other in content.other_isolates" :key="other.number">
                <em v-if="other.organism">{{ other.organism }}</em>
                <template v-else>{{ other.description }}</template>
                <template v-if="other.count"> · {{ other.count }}</template>
                <span class="text-muted-foreground">
                    · {{ other.significance_label }}</span
                >
            </p>
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
