<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import ReportBody from '@/components/domain/ReportBody.vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store as storeValidation } from '@/routes/validations';
import type { ReportContent, SampleHeader } from '@/types/clinical';

const props = defineProps<{
    sample: SampleHeader;
    status: string;
    preview: ReportContent;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Bandeja de validación', href: '/workbenches/validation' },
            { title: 'Validación', href: '#' },
        ],
    },
});

// "Validar" es la acción destacada y pide confirmación explícita (UX_UI.md).
const form = useForm({ confirm: false });

function validate() {
    form.post(storeValidation(props.sample.sample_study_id).url);
}
</script>

<template>
    <Head :title="`Validar ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <section class="rounded-lg border p-6">
            <p
                class="mb-4 text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Vista previa del informe
            </p>
            <ReportBody :content="props.preview" />
        </section>

        <form
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
            @submit.prevent="validate"
        >
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="form.confirm"
                    type="checkbox"
                    class="size-4"
                    data-test="confirm-review"
                />
                Revisé el informe y confirmo su validación.
            </label>
            <div class="grid justify-items-end gap-1">
                <Button
                    type="submit"
                    size="lg"
                    class="bg-green-700 text-white hover:bg-green-800"
                    :disabled="
                        !form.confirm ||
                        form.processing ||
                        props.status !== 'ready_for_validation'
                    "
                    data-test="validate"
                >
                    <Spinner v-if="form.processing" /><ShieldCheck v-else />
                    Validar y emitir informe
                </Button>
                <InputError
                    :message="
                        form.errors.confirm ||
                        (form.errors as Record<string, string>).study
                    "
                />
            </div>
        </form>
    </div>
</template>
