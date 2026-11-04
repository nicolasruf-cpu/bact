<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, Microscope } from '@lucide/vue';
import { computed } from 'vue';
import CriticalAlertCard from '@/components/domain/CriticalAlertCard.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { store as storeDirectExam } from '@/routes/direct-exams';
import { preliminary as preliminaryRoute } from '@/routes/reports';
import type {
    CriticalAlert,
    DirectExamReport,
    SampleHeader,
} from '@/types/clinical';

type Field = {
    code: string;
    label: string;
    multiple?: boolean;
    type?: 'options' | 'number' | 'text';
    unit?: string | null;
    options: { code: string; label: string; organisms?: boolean }[];
};

const props = defineProps<{
    sample: SampleHeader;
    studyStatus: string;
    settings: {
        label: string;
        fields: Field[];
        critical_if_organisms: boolean;
    };
    exam: DirectExamReport | null;
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
    can: Record<'perform' | 'communicate' | 'preliminary', boolean>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mesada de procesamiento',
                href: '/workbenches/processing',
            },
            { title: 'Examen directo', href: '#' },
        ],
    },
});

const form = useForm({
    results: Object.fromEntries(
        props.settings.fields.map((f) => [f.code, [] as string[]]),
    ) as Record<string, string[]>,
    description: '',
});

function toggle(field: Field, code: string) {
    const current = form.results[field.code] ?? [];
    if (current.includes(code)) {
        form.results[field.code] = current.filter((c) => c !== code);
    } else {
        form.results[field.code] = field.multiple ? [...current, code] : [code];
    }
}

const organismsSelected = computed(() =>
    props.settings.fields.some((field) =>
        (form.results[field.code] ?? []).some(
            (code) =>
                field.options.find((o) => o.code === code)?.organisms ?? false,
        ),
    ),
);

const hasSelection = computed(() =>
    Object.values(form.results).some((codes) => codes.length > 0),
);

function errorFor(code: string): string | undefined {
    return (form.errors as Record<string, string>)[`results.${code}`];
}

function submit() {
    form.transform((data) => ({
        ...data,
        results: Object.fromEntries(
            Object.entries(data.results).filter(([, codes]) => codes.length),
        ),
    })).post(storeDirectExam(props.sample.sample_study_id).url, {
        preserveScroll: true,
    });
}

const canIssuePreliminary = computed(
    () =>
        props.can.preliminary &&
        props.exam !== null &&
        ['in_process', 'preliminary_reported'].includes(props.studyStatus),
);

function issuePreliminary() {
    router.post(
        preliminaryRoute(props.sample.sample_study_id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Examen directo ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="grid content-start gap-4">
                <!-- Resultado registrado -->
                <section
                    v-if="props.exam"
                    class="grid gap-2 rounded-lg border p-4"
                    data-test="direct-exam-result"
                >
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        <Microscope class="size-5" /> {{ props.exam.label }}
                    </h1>
                    <p
                        v-for="line in props.exam.lines"
                        :key="line.field"
                        class="text-base"
                    >
                        <span class="text-muted-foreground"
                            >{{ line.field }}:</span
                        >
                        {{ line.values.join(', ') }}
                    </p>
                    <p v-if="props.exam.description">
                        {{ props.exam.description }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(props.exam.performed_at) }} ·
                        {{ props.exam.performed_by }}
                    </p>
                </section>

                <!-- Registro -->
                <form
                    v-else-if="props.can.perform"
                    class="grid gap-5 rounded-lg border p-4"
                    @submit.prevent="submit"
                >
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        <Microscope class="size-5" />
                        {{ props.settings.label }}
                    </h1>

                    <fieldset
                        v-for="field in props.settings.fields"
                        :key="field.code"
                        class="grid gap-2"
                    >
                        <legend class="mb-1 text-sm font-medium">
                            {{ field.label }}
                            <span
                                v-if="field.multiple"
                                class="font-normal text-muted-foreground"
                                >(una o más)</span
                            >
                        </legend>
                        <div
                            v-if="
                                field.type === 'number' || field.type === 'text'
                            "
                            class="flex items-center gap-2"
                        >
                            <input
                                type="text"
                                :inputmode="
                                    field.type === 'number' ? 'decimal' : 'text'
                                "
                                class="h-9 w-40 rounded-md border bg-background px-2 text-sm"
                                :value="
                                    (form.results[field.code] ?? [])[0] ?? ''
                                "
                                :data-test="`value-${field.code}`"
                                @input="
                                    form.results[field.code] = [
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ].filter((v) => v !== '')
                                "
                            />
                            <span
                                v-if="field.unit"
                                class="text-sm text-muted-foreground"
                                >{{ field.unit }}</span
                            >
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button
                                v-for="option in field.options"
                                :key="option.code"
                                type="button"
                                class="rounded-md border px-3 py-2 text-left text-sm"
                                :class="
                                    (form.results[field.code] ?? []).includes(
                                        option.code,
                                    )
                                        ? 'border-primary bg-primary/10 ring-1 ring-primary'
                                        : 'hover:border-primary'
                                "
                                :aria-pressed="
                                    (form.results[field.code] ?? []).includes(
                                        option.code,
                                    )
                                "
                                :data-test="`option-${field.code}-${option.code}`"
                                @click="toggle(field, option.code)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                        <InputError :message="errorFor(field.code)" />
                    </fieldset>
                    <InputError :message="form.errors.results" />

                    <div class="grid gap-1">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            placeholder="Opcional"
                            class="rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <p
                        v-if="
                            props.settings.critical_if_organisms &&
                            organismsSelected
                        "
                        class="rounded-md border border-red-500/50 bg-red-50 p-3 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
                        data-test="critical-warning"
                    >
                        Con microorganismos observados, este directo abre un
                        resultado crítico para comunicar.
                    </p>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            size="lg"
                            :disabled="!hasSelection || form.processing"
                            data-test="save-direct-exam"
                        >
                            <Spinner v-if="form.processing" /> Registrar examen
                            directo
                        </Button>
                    </div>
                </form>

                <p
                    v-else
                    class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
                >
                    El examen directo todavía no se registró.
                </p>

                <CriticalAlertCard
                    v-for="alert in props.criticalAlerts"
                    :key="alert.ulid"
                    :alert="alert"
                    :channels="props.channels"
                    :can-communicate="props.can.communicate"
                />
            </div>

            <aside
                class="grid content-start gap-3 rounded-lg border p-4 text-sm"
            >
                <h2 class="flex items-center gap-2 font-medium">
                    <FileText class="size-4" /> Informes
                </h2>
                <ul v-if="props.reports.length" class="grid gap-1">
                    <li
                        v-for="report in props.reports"
                        :key="report.ulid"
                        class="flex items-center justify-between gap-2"
                    >
                        <Link :href="report.url" class="underline"
                            >{{ report.type_label }} v{{ report.version }}</Link
                        >
                        <Badge variant="outline">{{
                            report.status_label
                        }}</Badge>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground">Sin informes.</p>
                <Button
                    v-if="canIssuePreliminary"
                    variant="outline"
                    data-test="issue-preliminary"
                    @click="issuePreliminary"
                    >Emitir informe preliminar</Button
                >
                <p
                    v-else-if="
                        props.can.preliminary &&
                        props.exam &&
                        props.studyStatus === 'pending'
                    "
                    class="text-xs text-muted-foreground"
                >
                    El preliminar se emite una vez sembrada la muestra.
                </p>
            </aside>
        </div>
    </div>
</template>
