<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import ReportBody from '@/components/domain/ReportBody.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/datetime';
import type { ReportContent } from '@/types/clinical';

const props = defineProps<{
    report: {
        ulid: string;
        version: number;
        type: string;
        status: string;
        validated_at: string;
        validated_by: string;
        content: ReportContent;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Informe', href: '#' }] },
});

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Informe ${props.report.content.sample.barcode}`" />

    <div class="mx-auto grid max-w-3xl gap-4 p-4">
        <div class="flex items-center justify-between gap-2 print:hidden">
            <p class="text-sm text-muted-foreground">
                {{ props.report.type }} v{{ props.report.version }} ·
                {{ props.report.status }}
            </p>
            <Button variant="outline" @click="print"
                ><Printer /> Imprimir</Button
            >
        </div>

        <section
            class="rounded-lg border bg-background p-8 print:border-0 print:p-0"
        >
            <ReportBody :content="props.report.content" />
            <p
                class="mt-6 border-t pt-3 text-xs text-muted-foreground"
                data-test="validation-signature"
            >
                Validado por {{ props.report.validated_by }} el
                {{ formatDateTime(props.report.validated_at) }} · Informe v{{
                    props.report.version
                }}
                · <span class="font-mono">{{ props.report.ulid }}</span>
            </p>
        </section>
    </div>
</template>
